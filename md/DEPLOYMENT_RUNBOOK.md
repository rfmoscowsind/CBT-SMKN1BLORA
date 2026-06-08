# CBT Deployment Runbook

## Routine Checks
```bash
curl -fsS http://192.168.16.120/up
systemctl is-active nginx php8.3-fpm cbt-worker cbt-scheduler
/usr/local/sbin/cbt-repl-check
```

On primary `192.168.16.121` as root:
```bash
systemctl is-active postgresql redis-server prometheus-postgres-exporter prometheus-redis-exporter
redis-cli -a '<redis-password>' INFO persistence
ls -lh /var/backups/cbt
ls -lh /var/lib/postgresql/wal_archive
```

## Standby Finalization
From the root console of `192.168.16.122`:
```bash
bash /tmp/cbt-standby-hardening.sh
```

## Safe Application Test
On web node:
```bash
sudo /usr/local/sbin/cbt-test-safe
```
This clears production config cache for isolated SQLite tests, restores config cache, and restarts the queue worker afterward. Do not run `php artisan test` directly while production config is cached.

## Deferred Public Edge
When the public domain is available: add DNS, open UFW port 443, issue TLS certificate, configure Nginx HTTPS redirect, enable Cloudflare proxy/WAF, and configure trusted proxies in Laravel.