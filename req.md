# 📋 SERVER REQUIREMENTS & INFRASTRUCTURE SETUP - CBT SMKN 1 BLORA

---

## 📊 INFRASTRUCTURE TOPOLOGY

```
┌─────────────────────────────────────────────────────────────────┐
│                         INTERNET (Public)                       │
│                              │                                  │
│                         Cloudflare WAF                           │
│                              │                                  │
└─────────────────────────────────────────────────────────────────┘
                               │
                        Nginx Load Balancer
                         (Rate Limiting)
                               │
                ┌──────────────────────────────┐
                │                              │
        ┌───────▼──────────┐        ┌────────▼────────┐
        │   SERVER 1       │        │   SERVER 2      │
        │  (Web/API/LB)    │◄──────►│  (DB/Redis/LB)  │
        │                  │   P2P  │                 │
        └──────────────────┘ /30    └─────────────────┘
        Baremetal/LXC        192.168.16.0/24 # vmbr0 shared bridge
        
        LXC-CBT (192.168.16.120)    LXC-DB-POSTGRE-1 (192.168.16.121)
        ├─ Nginx Reverse Proxy     ├─ PostgreSQL Primary
        ├─ PHP 8.2 / Octane        ├─ PostgreSQL Standby LXC-DB-POSTGRE-2 (192.168.16.122)
        ├─ Node.js 20+             ├─ Redis (Primary + Live Score)
        ├─ Docker/Compose          ├─ pg_basebackup (Backup Agent)
        └─ Monitoring Agent        └─ Replication Monitor
```

---

## 🖥️ SERVER 1: WEB/API/APPLICATION SERVER

### **A. Hardware Specifications**

| Komponen | Minimal | Recommended | Max Load |
|----------|---------|-------------|----------|
| **CPU** | 8 cores / 16 threads | 16 cores / 32 threads | 1500 concurrent |
| **RAM** | 16 GB | 32 GB | PHP-FPM + Octane buffer |
| **Storage** | 100 GB NVMe SSD | 200 GB NVMe SSD | Application + Cache |
| **Network** | 1Gbps | 1Gbps | Redundant NICs |

### **B. Operating System Setup**

```bash
# OS: Ubuntu 22.04 LTS or 24.04 LTS (Minimal installation)
# Kernel: Linux 5.15+ (for eBPF, io_uring support)

# 1. Update system
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git build-essential

# 2. Set timezone
sudo timedatectl set-timezone Asia/Jakarta
timedatectl

# 3. Configure network interfaces
# /etc/netplan/01-netcfg.yaml
network:
  version: 2
  ethernets:
    eth0:
      dhcp4: no
      addresses:
        - 192.168.16.120/24 # LXC-CBT  # WAN interface
      gateway4: 192.168.16.1
      nameservers:
        addresses: [8.8.8.8, 8.8.4.4]

sudo netplan apply
ip addr show

# 4. Enable IP forwarding (if needed for LB)
echo "net.ipv4.ip_forward = 1" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### **C. Software Installation**

#### **1. PHP 8.2 + Octane**

```bash
# Add Ondrej PPA
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2 + extensions
sudo apt install -y \
  php8.2-cli \
  php8.2-fpm \
  php8.2-mbstring \
  php8.2-xml \
  php8.2-pgsql \
  php8.2-redis \
  php8.2-bcmath \
  php8.2-curl \
  php8.2-json \
  php8.2-gd \
  php8.2-intl \
  php8.2-zip \
  php8.2-soap

# Start PHP-FPM
sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm
sudo systemctl status php8.2-fpm

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
composer --version
```

#### **2. Node.js 20+**

```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verify
node --version   # v20.x.x
npm --version    # 10.x.x

# Global packages
sudo npm install -g pm2 yarn
pm2 startup
```

#### **3. Nginx (Reverse Proxy + Load Balancer)**

```bash
sudo apt install -y nginx

# Configure Nginx
# /etc/nginx/nginx.conf
user www-data;
worker_processes auto;  # Use all CPU cores
worker_connections 10000;
multi_accept on;

http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 50M;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=100r/s;
    limit_req_zone $binary_remote_addr zone=login_limit:10m rate=10r/s;

    # Upstream Octane servers
    upstream octane_app {
        server 127.0.0.1:8000;
        server 127.0.0.1:8001;
        server 127.0.0.1:8002;
        keepalive 64;
    }

    server {
        listen 80 default_server;
        listen [::]:80 default_server;
        server_name cbt.local api.cbt.local;

        # Redirect to HTTPS
        return 301 https://$server_name$request_uri;
    }

    server {
        listen 443 ssl http2 default_server;
        listen [::]:443 ssl http2 default_server;
        server_name cbt.local api.cbt.local;

        ssl_certificate /etc/letsencrypt/live/cbt.local/fullchain.pem;
        ssl_certificate_key /etc/letsencrypt/live/cbt.local/privkey.pem;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!MD5;
        ssl_prefer_server_ciphers on;

        root /var/www/cbt/public;
        index index.php;

        # API routes
        location /api/ {
            limit_req zone=api_limit burst=20 nodelay;
            
            try_files $uri $uri/ /index.php?$query_string;
            proxy_pass http://octane_app;
            proxy_http_version 1.1;
            proxy_set_header Connection "";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Login endpoint - stricter rate limit
        location /api/auth/login {
            limit_req zone=login_limit burst=5 nodelay;
            proxy_pass http://octane_app;
        }

        # Static files (images, css, js)
        location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp)$ {
            expires 30d;
            add_header Cache-Control "public, immutable";
        }

        # Health check endpoint
        location /health {
            access_log off;
            proxy_pass http://octane_app;
        }

        error_log /var/log/nginx/cbt_error.log warn;
        access_log /var/log/nginx/cbt_access.log combined buffer=32k;
    }
}

# Validate & restart
sudo nginx -t
sudo systemctl enable nginx
sudo systemctl restart nginx
```

#### **4. Docker & Docker Compose**

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
  -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

docker --version
docker-compose --version
```

### **D. Application Setup (Laravel + Octane)**

```bash
# Clone repository
cd /var/www/cbt
git clone https://github.com/your-org/cbt-system.git .

# Install Laravel
composer install --no-dev --optimize-autoloader

# Environment configuration
cp .env.example .env
php artisan key:generate

# Database setup (see Server 2 section)
php artisan migrate --force
php artisan seed:ProductionSeeder

# Octane start (with multiple workers)
php artisan octane:start --server=frankenphp \
  --port=8000 \
  --workers=16 \
  --max-requests=5000 \
  --timeout=60

# Or with PM2 for process management
pm2 start --name octane-worker-1 "php artisan octane:start --server=frankenphp --port=8000"
pm2 start --name octane-worker-2 "php artisan octane:start --server=frankenphp --port=8001"
pm2 start --name octane-worker-3 "php artisan octane:start --server=frankenphp --port=8002"
pm2 save
```

### **E. Storage & File Permissions**

```bash
# Create storage directories
mkdir -p storage/{logs,app,framework/{cache,sessions,views}}
mkdir -p bootstrap/cache

# Set permissions
sudo chown -R www-data:www-data /var/www/cbt
sudo chmod -R 755 /var/www/cbt
sudo chmod -R 775 /var/www/cbt/storage
sudo chmod -R 775 /var/www/cbt/bootstrap/cache

# Setup image upload directory
mkdir -p storage/app/public/soal-images
sudo chown -R www-data:www-data storage/app/public
```

---

## 🗄️ SERVER 2: DATABASE & IN-MEMORY DATA STORE

### **A. Hardware Specifications**

| Komponen | Minimal | Recommended | Reasoning |
|----------|---------|-------------|-----------|
| **CPU** | 8 cores / 16 threads | 16 cores / 32 threads | PostgreSQL + Redis |
| **RAM** | 32 GB | 64 GB | `shared_buffers` + Redis memory |
| **Storage** | 250 GB Enterprise NVMe | 500 GB NVMe (RAID1) | WAL + Data files |
| **I/O** | 10K IOPS minimum | 20K IOPS | High-concurrency writes |

### **B. Operating System Setup**

```bash
# Same as Server 1
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone Asia/Jakarta

# /etc/netplan/01-netcfg.yaml
network:
  version: 2
  ethernets:
    eth0:
      dhcp4: no
      addresses:
        - 192.168.16.121/24 # LXC-DB-POSTGRE-1 Primary  # WAN (backup access)
      gateway4: 192.168.16.1

sudo netplan apply
```

### **C. PostgreSQL 16 Installation & Configuration**

#### **1. Installation**

```bash
# Add PostgreSQL repository
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update

# Install PostgreSQL 16
sudo apt install -y postgresql-16 postgresql-client-16

# Start service
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

#### **2. Primary Server Configuration (/etc/postgresql/15/main/postgresql.conf)**

```bash
# Performance tuning
max_connections = 300
shared_buffers = 16GB              # 25% of total RAM
effective_cache_size = 48GB        # 75% of total RAM
maintenance_work_mem = 4GB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1              # For NVMe
effective_io_concurrency = 200

# Replication settings
wal_level = replica
max_wal_senders = 5
wal_keep_size = 2GB
hot_standby = on

# Logging
log_connections = on
log_disconnections = on
log_statement = 'all'
log_duration = on
log_min_duration_statement = 1000   # Log queries > 1 second
log_checkpoints = on
log_autovacuum_min_duration = 0

# WAL archiving (optional, for backup)
archive_mode = on
archive_command = 'mkdir -p /var/lib/postgresql/wal_archive && cp %p /var/lib/postgresql/wal_archive/%f'

# Restart PostgreSQL
sudo systemctl restart postgresql
```

#### **3. Replication User & Access**

```bash
# Create repluser user
sudo -u postgres psql << EOF
CREATE USER repluser WITH REPLICATION ENCRYPTED PASSWORD '<SET_CBT_SECRET_ENV>';
SELECT * FROM pg_user WHERE usename = 'repluser';
EOF

# /etc/postgresql/15/main/pg_hba.conf
# Add repluser line
echo "host    repluser     repluser     192.168.16.0/24 # vmbr0 shared bridge     md5" | \
  sudo tee -a /etc/postgresql/15/main/pg_hba.conf

# Reload configuration
sudo -u postgres psql -c "SELECT pg_reload_conf();"
```

#### **4. Database Setup**

```bash
# Create cbt database and extensions
sudo -u postgres psql << EOF
CREATE DATABASE cbt OWNER postgres;
\c cbt
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "btree_gin";

-- Create public schema
CREATE SCHEMA public AUTHORIZATION postgres;
GRANT ALL ON SCHEMA public TO postgres;
EOF
```

### **D. PostgreSQL Standby (Hot Standby - Read Only)**

#### **1. Create Basebackup on Server 1**

```bash
# On Server 1, SSH to LXC-DB-POSTGRE-2 
ssh-keygen -t ed25519 -f ~/.ssh/id_postgres_repl

# Copy public key to Server 2 postgres user
cat ~/.ssh/id_postgres_repl.pub >> /var/lib/postgresql/.ssh/authorized_keys

# Test connection
ssh -i ~/.ssh/id_postgres_repl postgres@192.168.16.121 "echo OK"
```

#### **2. Setup Standby Node**

```bash
# On Server 2
sudo -u postgres bash << EOF
# Stop any running PostgreSQL
pg_ctlcluster 16 main stop

# Remove old cluster if exists
rm -rf /var/lib/postgresql/15/main/*

# Create basebackup from primary
pg_basebackup -h 192.168.16.120 -U repluser -D /var/lib/postgresql/15/main -Pv -W --wal-method=stream

# Create recovery configuration
cat > /var/lib/postgresql/15/main/recovery.conf << 'RECOVERY'
standby_mode = 'on'
primary_conninfo = 'host=192.168.16.120 port=5432 user=repluser password=<SET_CBT_SECRET_ENV>'
recovery_target_timeline = 'latest'
RECOVERY

# Set permissions
chown -R postgres:postgres /var/lib/postgresql/15/main
chmod 700 /var/lib/postgresql/15/main

# Start standby
pg_ctlcluster 16 main start

# Monitor repluser status
psql -U postgres -d postgres -c "SELECT client_addr, replay_lsn, flush_lsn FROM pg_stat_repluser;"
EOF
```

#### **3. Standby Configuration (/etc/postgresql/15/main/postgresql.conf)**

```bash
# Same as primary, but:
hot_standby = on                    # Allow read queries
hot_standby_feedback = on           # Prevent query conflicts
wal_receiver_timeout = 60s

# Restart
sudo systemctl restart postgresql
```

#### **4. Monitor Replication Status**

```bash
# On Primary (Server 1)
sudo -u postgres psql << EOF
SELECT 
    client_addr,
    state,
    sync_state,
    write_lag,
    flush_lag,
    replay_lag
FROM pg_stat_repluser;
EOF

# Expected output:
# client_addr    | state    | sync_state | write_lag | flush_lag | replay_lag
# 192.168.16.121   | streaming| async      | 00:00:00.001234 | ...
```

### **E. Redis 7 Installation & Configuration**

#### **1. Installation**

```bash
# Install Redis
curl -fsSL https://packages.redis.io/gpg.sh | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] https://packages.redis.io/deb $(lsb_release -cs) main" | \
  sudo tee /etc/apt/sources.list.d/redis.list
sudo apt update
sudo apt install -y redis-server

sudo systemctl enable redis-server
sudo systemctl start redis-server
```

#### **2. Configuration (/etc/redis/redis.conf)**

```bash
# Memory management
maxmemory 16gb                      # 50% of available RAM on Server 2
maxmemory-policy allkeys-lru        # Evict least recently used keys

# Persistence
save 900 1                          # Save if 1+ keys changed in 15 min
save 300 10                         # Save if 10+ keys changed in 5 min
save 60 10000                       # Save if 10000+ keys changed in 60 sec

appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec

# Replication (if needed)
# replicaof <primary_ip> 6379
# masterauth <password>

# Bind to all interfaces (but use firewall)
bind 0.0.0.0
protected-mode no

# Logging
logfile /var/log/redis/redis-server.log
loglevel notice

# TCP settings
tcp-keepalive 300
timeout 0
```

#### **3. Restart & Verify**

```bash
sudo systemctl restart redis-server
redis-cli ping                      # Should return PONG
redis-cli info memory | grep used   # Check memory usage
```

### **F. Live Score Database Connection**

#### **1. Create Read-Only User**

```bash
# On Primary (Server 1)
sudo -u postgres psql << EOF
-- Create read-only user for live score queries
CREATE USER live_score_reader WITH PASSWORD '<SET_CBT_SECRET_ENV>';

-- Grant read permissions
GRANT CONNECT ON DATABASE cbt TO live_score_reader;
GRANT USAGE ON SCHEMA public TO live_score_reader;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO live_score_reader;

-- Default for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO live_score_reader;
EOF
```

#### **2. Configure Server 1 to Query Standby**

```php
// config/database.php (Laravel)
'pgsql_standby' => [
    'driver' => 'pgsql',
    'host' => '192.168.16.122',           // Standby LXC-DB-POSTGRE-2
    'port' => 5432,
    'database' => 'cbt',
    'username' => 'live_score_reader',
    'password' => env('DB_STANDBY_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => 'prefer',
],
```

#### **3. Use in Laravel**

```php
// Get live score dari read-only standby (tidak membebani primary)
$liveScore = DB::connection('pgsql_standby')
    ->table('jawaban_siswas')
    ->where('sesi_ujian_id', $sesiId)
    ->where('scoring_status', 'auto_scored')
    ->sum('skor');

// Write operations tetap ke primary
$jawaban = DB::connection('pgsql')->table('jawaban_siswas')->insert($data);
```

---

## 🌐 NETWORK & FIREWALL CONFIGURATION

### **A. Firewall Rules (UFW)**

#### **Server 1 (Web/API)**

```bash
# Reset dan enable
sudo ufw reset
sudo ufw enable

# SSH
sudo ufw allow 22/tcp comment "SSH"

# HTTP/HTTPS
sudo ufw allow 80/tcp comment "HTTP"
sudo ufw allow 443/tcp comment "HTTPS"

# Internal network (Server 2)
sudo ufw allow from 192.168.16.121/32 to any port 5432 comment "PostgreSQL from Server 2"
sudo ufw allow from 192.168.16.121/32 to any port 6379 comment "Redis from Server 2"

# Deny all incoming (default)
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Status
sudo ufw status verbose
```

#### **Server 2 (Database)**

```bash
# SSH
sudo ufw allow 22/tcp comment "SSH"

# PostgreSQL (only from Server 1)
sudo ufw allow from 192.168.16.120/32 to any port 5432 comment "PostgreSQL from Server 1"

# Redis (only from Server 1)
sudo ufw allow from 192.168.16.120/32 to any port 6379 comment "Redis from Server 1"

# Allow repluser from Server 1
sudo ufw allow from 192.168.16.120/32 to any port 5432 comment "PostgreSQL Replication"

# Status
sudo ufw status
```

### **B. Network Monitoring**

```bash
# Check P2P connectivity
ping 192.168.16.121          # From Server 1 to Server 2
ping 192.168.16.120          # From Server 2 to Server 1

# Check bandwidth
iperf3 -s                  # On Server 2
iperf3 -c 192.168.16.121     # On Server 1 (should see ~1Gbps)

# Monitor network interface
ifstat -i eth1 1
```

---

## 📊 MONITORING & OBSERVABILITY

### **A. Application Monitoring**

#### **1. Laravel Telescope (Development/Staging)**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

#### **2. Prometheus + Node Exporter**

```bash
# Install node exporter
wget https://github.com/prometheus/node_exporter/releases/download/v1.6.1/node_exporter-1.6.1.linux-amd64.tar.gz
tar xvf node_exporter-1.6.1.linux-amd64.tar.gz
sudo mv node_exporter-1.6.1.linux-amd64/node_exporter /usr/local/bin/
sudo useradd --no-create-home --shell /bin/false node_exporter

# Service file
sudo tee /etc/systemd/system/node_exporter.service << EOF
[Unit]
Description=Node Exporter
After=network.target

[Service]
User=node_exporter
Group=node_exporter
Type=simple
ExecStart=/usr/local/bin/node_exporter

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable node_exporter
sudo systemctl start node_exporter

# Verify
curl localhost:9100/metrics | head
```

### **B. LXC-DB-POSTGRE-2 

#### **1. PostgreSQL Metrics**

```bash
# Install postgres_exporter
wget https://github.com/prometheusresearch/postgres_exporter/releases/download/v0.11.1/postgres_exporter_linux-amd64
chmod +x postgres_exporter_linux-amd64
sudo mv postgres_exporter_linux-amd64 /usr/local/bin/postgres_exporter

# Config
sudo tee /etc/postgres_exporter.env << EOF
DATA_SOURCE_NAME="postgresql://postgres:password@localhost:5432/postgres?sslmode=disable"
PG_EXPORTER_INCLUDE_DATABASES="cbt"
EOF

# Service
sudo tee /etc/systemd/system/postgres_exporter.service << EOF
[Unit]
Description=PostgreSQL Exporter
After=network.target

[Service]
EnvironmentFile=/etc/postgres_exporter.env
ExecStart=/usr/local/bin/postgres_exporter
Type=simple

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable postgres_exporter
sudo systemctl start postgres_exporter
```

#### **2. Redis Monitoring**

```bash
# Redis is exposed on port 6379
# Use redis-cli to monitor
redis-cli INFO stats
redis-cli INFO memory

# Automated monitoring script
cat > /usr/local/bin/monitor-redis.sh << 'EOF'
#!/bin/bash
while true; do
    echo "$(date): Memory usage: $(redis-cli INFO memory | grep used_memory_human)"
    echo "$(date): Connected clients: $(redis-cli INFO clients | grep connected_clients)"
    sleep 60
done
EOF

chmod +x /usr/local/bin/monitor-redis.sh
```

### **C. Centralized Logging (ELK Stack - Optional)**

```bash
# Install Filebeat on both servers
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.0.0-linux-x86_64.tar.gz
tar xzf filebeat-8.0.0-linux-x86_64.tar.gz

# Config filebeat.yml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/log/nginx/cbt_access.log
    - /var/log/nginx/cbt_error.log
    - /var/www/cbt/storage/logs/*.log
    - /var/log/postgresql/postgresql.log

output.elasticsearch:
  hosts: ["elastic.local:9200"]
```

---

## ✅ DEPLOYMENT CHECKLIST

### **Pre-Deployment (Server Setup)**

- [ ] Both servers have correct OS (Ubuntu 22.04/24.04 LTS)
- [ ] Timezone set to Asia/Jakarta on both
- [ ] Direct P2P network connectivity verified (ping test)
- [ ] SSH key-based auth configured
- [ ] Firewall rules applied and tested
- [ ] Time sync via NTP (ntpdate)

### **Server 1 Setup**

- [ ] PHP 8.2 + required extensions installed
- [ ] Node.js 20+ installed
- [ ] Nginx with SSL/TLS configured
- [ ] Docker & Docker Compose installed
- [ ] Laravel application cloned and configured
- [ ] Storage directories created with correct permissions
- [ ] Octane workers running (min 16 workers)
- [ ] Monitoring agents configured

### **Server 2 Setup**

- [ ] PostgreSQL 16 installed and configured
- [ ] Replication user created
- [ ] Hot Standby basebackup completed
- [ ] Replication lag verified (< 1 second)
- [ ] Redis installed and tuned
- [ ] Live score read-only user created
- [ ] Both pg_hba.conf and redis.conf secured
- [ ] Monitoring agents installed

### **Network & Security**

- [ ] UFW rules applied and tested
- [ ] PostgreSQL repluser working (check pg_stat_repluser)
- [ ] Redis accessible from Server 1 only
- [ ] SSL/TLS certificates installed and valid
- [ ] Cloudflare CDN configured (if using)
- [ ] Rate limiting rules tested

### **Pre-Launch Testing**

- [ ] Database migrations run successfully
- [ ] Seeder data loaded
- [ ] API endpoints responding (curl tests)
- [ ] Replication lag under 100ms
- [ ] Load test: 1500 concurrent users
- [ ] Offline sync mechanism tested
- [ ] Image compression verified
- [ ] Monitoring dashboard accessible

---

## 🚨 TROUBLESHOOTING

### **PostgreSQL Replication Issues**

```bash
# Check repluser status on primary
sudo -u postgres psql -c "SELECT client_addr, state, replay_lsn FROM pg_stat_repluser;"

# Check WAL position
sudo -u postgres psql -c "SELECT pg_current_wal_lsn();"

# Restart standby
sudo -u postgres pg_ctlcluster 16 main stop
sudo -u postgres pg_ctlcluster 16 main start

# Check logs
tail -f /var/log/postgresql/postgresql.log
```

### **Redis Memory Issues**

```bash
# Check memory
redis-cli INFO memory

# See top keys by size
redis-cli --bigkeys

# Flush if needed (CAUTION)
redis-cli FLUSHDB  # Only for testing!
```

### **Octane Worker Issues**

```bash
# Check Octane process
ps aux | grep octane

# Kill and restart
pkill -f "artisan octane"
php artisan octane:start --server=frankenphp --workers=16 &
```

---

## 📈 PERFORMANCE TUNING

### **PostgreSQL Optimization**

```bash
# Analyze query performance
EXPLAIN ANALYZE
SELECT * FROM sesi_ujians WHERE status = 'aktif';

# Create indexes
CREATE INDEX idx_sesi_ujians_status ON sesi_ujians(status);
CREATE INDEX idx_jawaban_siswas_sesi ON jawaban_siswas(sesi_ujian_id);
CREATE INDEX idx_jawaban_siswas_scoring ON jawaban_siswas(sesi_ujian_id, scoring_status);
```

### **Redis Optimization**

```bash
# Monitor key expiration
redis-cli --bigkeys
redis-cli --scan --pattern "queue_jawaban_*" | wc -l

# Optimize memory
redis-cli MEMORY DOCTOR
```

### **Nginx Optimization**

```nginx
# gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript;
```

---

*Last Updated: June 2026*
*Status: Production Ready*
# SERVER REQUIREMENTS & INFRASTRUCTURE SETUP - CBT SMKN 1 BLORA

## Status Implementasi Saat Ini - 2026-06-04

Bagian ini menjadi acuan operasional terbaru. Isi lama di bawah tetap dipertahankan sebagai rancangan awal/historis.

### Topologi Production Aktual

1. Server aplikasi CBT utama:
   - Host: `192.168.16.120`
   - Nama host: `LXC-CBT`
   - Direktori aplikasi: `/var/www/html`
   - Domain: `https://cbt.madnnet.my.id`
   - Service aktif: `nginx`, `php8.3-fpm`, `php8.2-fpm`, `redis-server`
   - Node.js/Vite production build dijalankan di server ini.

2. Server database primary:
   - Host: `192.168.16.121`
   - Nama host: `LXC-DB-POSTGRE-1`
   - PostgreSQL aktif dan menerima koneksi.
   - Redis tidak lagi menjadi backend session/cache/queue aplikasi.

3. Server hot standby:
   - Host: `192.168.16.122`
   - Port `22` dan `5432` terdeteksi terbuka dari jaringan server CBT.
   - Akses langsung dari workstation dapat timeout tergantung jalur jaringan.

### Redis Production Aktual

Redis aplikasi sekarang berjalan lokal di server CBT:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Catatan penting:
- Redis listen di `127.0.0.1:6379`.
- Laravel Redis ping dari server aplikasi berhasil.
- Redis remote `192.168.16.121:6379` tidak digunakan untuk aplikasi.
- Jangan menjalankan `php artisan config:cache` dari Windows share karena dapat memasukkan path `T:\...` ke `bootstrap/cache/config.php` dan memicu 500 di Linux. Jika diperlukan, jalankan dari `/var/www/html` di server Linux.

### Worker dan Service Production Aktual

Worker permanen yang digunakan:

```text
cbt-worker.service      -> php artisan queue:work redis --queue=answers,default --sleep=1 --tries=3 --timeout=60
cbt-scheduler.service   -> php artisan schedule:work
cbt-radar.service       -> php artisan cbt:radar-worker
```

Operasional:

```bash
sudo systemctl restart cbt-worker.service cbt-scheduler.service cbt-radar.service
sudo systemctl status cbt-worker.service cbt-scheduler.service cbt-radar.service
```

Jangan menjalankan worker manual paralel dengan `nohup` jika service permanen aktif, karena dapat membuat queue/radar/scheduler duplikat.

### Build Frontend Production

Build frontend production dilakukan di server aplikasi:

```bash
cd /var/www/html
npm run build
sudo systemctl reload php8.3-fpm php8.2-fpm
```

Build lokal Windows tidak menjadi acuan karena dependency Vite di share tidak selalu lengkap.

### Smoke Test Minimal

Ekspektasi:
- `/login` mengembalikan `200`.
- `/dashboard` redirect ke login jika belum login.
- `/vue/management/hasil` dan `/vue/management/arsip-hasil` redirect ke login jika belum login.

### Pembagian Hasil, Download, dan Arsip

1. `Hasil Ujian`
   - URL: `/vue/management/hasil`
   - Hanya untuk ujian yang belum diarsipkan.
   - Backend filter: `jadwal_ujians.diarsipkan_at IS NULL`.
   - Setelah pilih kelas, server hanya mengirim daftar ujian.
   - Detail nilai siswa baru dimuat setelah memilih satu `jadwal_id`.

2. `Download Hasil`
   - URL: `/vue/management/download-hasil`
   - Untuk preview/download PDF hasil ujian yang belum diarsipkan.
   - Backend filter: `jadwal_ujians.diarsipkan_at IS NULL`.
   - Catatan download disimpan di `hasil_ujian_unduhans`.

3. `Arsip Hasil`
   - URL: `/vue/management/arsip-hasil`
   - Halaman baru khusus ujian yang sudah diarsipkan.
   - Backend filter: `jadwal_ujians.diarsipkan_at IS NOT NULL`.
   - Filter: Tahun -> Tingkat -> Jurusan -> Rombel -> Ujian.
   - Daftar arsip tidak langsung memuat nilai siswa; detail nilai baru dimuat setelah memilih ujian.

### Device Lock dan Session Security Aktual

Device lock memakai:
- Fingerprint browser/device.
- Local storage anchor token.
- User agent, resolusi layar, timezone, platform, dan sinyal environment.
- Lock server-side pada user jika fingerprint tidak cocok.

Perilaku:
- Siswa tertaut ke satu perangkat saat login.
- Jika mencoba perangkat lain, user dapat dikunci dan sesi web/API diputus.
- Jika terkunci, siswa otomatis logout dan harus login ulang setelah admin reset/unlock.
- Redis session dihancurkan lewat session handler, bukan hanya menghapus row DB.
- API JWT siswa wajib membawa fingerprint sehingga tidak menjadi bypass.

### Monitoring dan Permission

Endpoint monitoring web dibatasi:
- Permission `monitor-exams`, atau
- Role `SuperAdmin`, `Admin`, `Pengawas`.

### Catatan Keamanan Operasional

- Jangan menyimpan kredensial plaintext di repo/root project.
- File sementara seperti `cred.txt` harus dihapus atau dipindahkan ke vault setelah operasional.
- Jangan menampilkan secret di dokumentasi final, log, atau screenshot.

---
