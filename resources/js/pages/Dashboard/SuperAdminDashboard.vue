<template>
    <div id="wrapper">
        <div class="sidebar">
            <div class="sidebar-brand">
                <img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo">
                <div>
                    <div class="fw-bold fs-6 text-white">SMKN 1 BLORA</div>
                    <div style="font-size: 0.7rem; color: #94a3b8;">CBT Enterprise Server</div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <div class="px-4 text-uppercase fw-bold mb-2 mt-2" style="font-size: 0.7rem; color: #475569;">Menu Utama</div>
                <router-link to="/vue/dashboard/superadmin" class="nav-item-custom active"><i class="fa-solid fa-chart-pie"></i> Dashboard</router-link>
                <router-link to="/vue/monitoring/radar" class="nav-item-custom"><i class="fa-solid fa-tower-broadcast"></i> Radar Real-Time</router-link>
                
                <div class="px-4 text-uppercase fw-bold mb-2 mt-4" style="font-size: 0.7rem; color: #475569;">Master & Akses</div>
                <router-link to="/vue/management/staff" class="nav-item-custom"><i class="fa-solid fa-users-gear"></i> Manajemen Staf</router-link>
                <router-link to="/vue/management/master" class="nav-item-custom"><i class="fa-solid fa-school"></i> Master Sekolah</router-link>
                
                <div class="px-4 text-uppercase fw-bold mb-2 mt-4" style="font-size: 0.7rem; color: #475569;">Manajemen Ujian</div>
                <router-link to="/vue/management/siswa" class="nav-item-custom"><i class="fa-solid fa-users-viewfinder"></i> Manajemen Siswa</router-link>
                <router-link to="/vue/management/soal" class="nav-item-custom"><i class="fa-solid fa-file-circle-check"></i> Bank Soal</router-link>
                <router-link to="/vue/management/jadwal" class="nav-item-custom"><i class="fa-solid fa-calendar-days"></i> Jadwal Ujian</router-link>
                <router-link to="/vue/management/hasil" class="nav-item-custom"><i class="fa-solid fa-square-poll-vertical"></i> Hasil Ujian</router-link>
                <router-link to="/vue/management/download-hasil" class="nav-item-custom"><i class="fa-solid fa-file-pdf"></i> Download Hasil</router-link>
                <router-link to="/vue/management/fingerprints" class="nav-item-custom"><i class="fa-solid fa-fingerprint"></i> Kunci Perangkat</router-link>
            </div>
            
            <div class="p-3 border-top" style="border-color: #1e293b !important;">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white me-2" style="width: 35px; height: 35px;">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div class="small">
                        <div class="text-white fw-bold">{{ currentUser.name }}</div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">{{ currentUser.role }} Role</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content bg-light">
            
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark me-3">System Overview</h5>
                    <span class="badge bg-primary rounded-pill bg-opacity-10 text-primary border border-primary px-3">
                        <i class="fa-solid fa-network-wired me-1"></i> Mode: Baremetal P2P
                    </span>
                </div>
                <div>
                    <button class="btn btn-light border rounded-circle me-2 position-relative">
                        <i class="fa-regular fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                    </button>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Logout
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Total Siswa Aktif</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.total_siswa }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-green"><i class="fa-solid fa-laptop-file"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Ujian Berlangsung</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.ujian_berlangsung }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-purple"><i class="fa-solid fa-user-check"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Sesi Aktif Ujian</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.peserta_aktif }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Siswa Selesai</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.siswa_selesai }}</div>
                                <div class="text-muted small">Sesi ujian selesai</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="section-header">
                            <div>
                                <h6 class="fw-bold mb-1">Server Overview</h6>
                                <small class="text-muted">Status diperbarui otomatis setiap 30 detik.</small>
                            </div>
                            <span class="badge rounded-pill px-3 py-2" :class="overviewHealthy ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger'">
                                <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem;"></i>
                                {{ overviewHealthy ? 'Normal' : 'Perlu dicek' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="overview-card">
                            <div class="overview-card-header">
                                <div>
                                    <div class="overview-title">{{ overview.main_server.name }}</div>
                                    <div class="overview-host">{{ overview.main_server.host }}</div>
                                </div>
                                <span class="status-pill" :class="statusClass(overview.main_server.status)">{{ statusText(overview.main_server.status) }}</span>
                            </div>
                            <div class="metric-grid">
                                <div>
                                    <div class="metric-label">Load 1m</div>
                                    <div class="metric-value">{{ overview.main_server.load.one }}</div>
                                </div>
                                <div>
                                    <div class="metric-label">Load 5m</div>
                                    <div class="metric-value">{{ overview.main_server.load.five }}</div>
                                </div>
                                <div>
                                    <div class="metric-label">Load 15m</div>
                                    <div class="metric-value">{{ overview.main_server.load.fifteen }}</div>
                                </div>
                            </div>
                            <div class="progress-row">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Memory</span><span>{{ percentText(overview.main_server.memory.percent) }}</span>
                                </div>
                                <div class="progress"><div class="progress-bar bg-primary" :style="{ width: percentWidth(overview.main_server.memory.percent) }"></div></div>
                                <div class="overview-foot">{{ sizeText(overview.main_server.memory.used_mb, 'MB') }} / {{ sizeText(overview.main_server.memory.total_mb, 'MB') }}</div>
                            </div>
                            <div class="progress-row mb-0">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Disk</span><span>{{ percentText(overview.main_server.disk.percent) }}</span>
                                </div>
                                <div class="progress"><div class="progress-bar bg-info" :style="{ width: percentWidth(overview.main_server.disk.percent) }"></div></div>
                                <div class="overview-foot">{{ sizeText(overview.main_server.disk.used_gb, 'GB') }} / {{ sizeText(overview.main_server.disk.total_gb, 'GB') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="overview-card">
                            <div class="overview-card-header">
                                <div>
                                    <div class="overview-title">{{ overview.primary_db.name }}</div>
                                    <div class="overview-host">{{ overview.primary_db.host }}:{{ overview.primary_db.port }}</div>
                                </div>
                                <span class="status-pill" :class="statusClass(overview.primary_db.status)">{{ statusText(overview.primary_db.status) }}</span>
                            </div>
                            <div class="db-meta">
                                <div><span>Database</span><strong>{{ overview.primary_db.database }}</strong></div>
                                <div><span>Role</span><strong>{{ overview.primary_db.role }}</strong></div>
                                <div><span>Latency</span><strong>{{ latencyText(overview.primary_db.latency_ms) }}</strong></div>
                                <div><span>DB Size</span><strong>{{ overview.primary_db.size }}</strong></div>
                                <div><span>Connections</span><strong>{{ overview.primary_db.connections ?? '-' }}</strong></div>
                            </div>
                            <div class="overview-foot mt-3">DB Time: {{ overview.primary_db.db_time || '-' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="overview-card">
                            <div class="overview-card-header">
                                <div>
                                    <div class="overview-title">{{ overview.secondary_db.name }}</div>
                                    <div class="overview-host">{{ overview.secondary_db.host }}:{{ overview.secondary_db.port }}</div>
                                </div>
                                <span class="status-pill" :class="statusClass(overview.secondary_db.status)">{{ statusText(overview.secondary_db.status) }}</span>
                            </div>
                            <div class="db-meta">
                                <div><span>Database</span><strong>{{ overview.secondary_db.database }}</strong></div>
                                <div><span>Role</span><strong>{{ overview.secondary_db.role }}</strong></div>
                                <div><span>Latency</span><strong>{{ latencyText(overview.secondary_db.latency_ms) }}</strong></div>
                                <div><span>DB Size</span><strong>{{ overview.secondary_db.size }}</strong></div>
                                <div><span>Connections</span><strong>{{ overview.secondary_db.connections ?? '-' }}</strong></div>
                            </div>
                            <div class="overview-foot mt-3 text-truncate" :title="overview.secondary_db.message">Info: {{ overview.secondary_db.message || '-' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="overview-card">
                            <div class="overview-card-header">
                                <div>
                                    <div class="overview-title">{{ overview.redis_primary.name }}</div>
                                    <div class="overview-host">{{ overview.redis_primary.host }}:{{ overview.redis_primary.port }}</div>
                                </div>
                                <span class="status-pill" :class="statusClass(overview.redis_primary.status)">{{ statusText(overview.redis_primary.status) }}</span>
                            </div>
                            <div class="db-meta">
                                <div><span>Memory</span><strong>{{ overview.redis_primary.memory.used }}</strong></div>
                                <div><span>Peak</span><strong>{{ overview.redis_primary.memory.peak }}</strong></div>
                                <div><span>QPS</span><strong>{{ qpsText(overview.redis_primary.qps) }}</strong></div>
                                <div><span>Clients</span><strong>{{ overview.redis_primary.clients ?? '-' }}</strong></div>
                                <div><span>Hit Rate</span><strong>{{ percentText(overview.redis_primary.hit_rate) }}</strong></div>
                                <div><span>Uptime</span><strong>{{ overview.redis_primary.uptime_days ?? '-' }} hari</strong></div>
                            </div>
                            <div class="redis-keyspace mt-3" v-if="overview.redis_primary.keyspace.length">
                                <div class="metric-label mb-2">Keyspace</div>
                                <div class="keyspace-row" v-for="db in overview.redis_primary.keyspace" :key="db.db">
                                    <span>{{ db.db }}</span>
                                    <strong>{{ db.keys }} keys</strong>
                                    <small>{{ db.expires }} exp</small>
                                </div>
                            </div>
                            <div class="overview-foot mt-3">Frag: {{ overview.redis_primary.memory.fragmentation ?? '-' }} | Max: {{ overview.redis_primary.memory.max }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="overview-card">
                            <div class="overview-card-header">
                                <div>
                                    <div class="overview-title">{{ overview.redis_backup.name }}</div>
                                    <div class="overview-host">{{ overview.redis_backup.host }}:{{ overview.redis_backup.port }}</div>
                                </div>
                                <span class="status-pill" :class="statusClass(overview.redis_backup.status)">{{ statusText(overview.redis_backup.status) }}</span>
                            </div>
                            <div class="db-meta">
                                <div><span>Memory</span><strong>{{ overview.redis_backup.memory.used }}</strong></div>
                                <div><span>Peak</span><strong>{{ overview.redis_backup.memory.peak }}</strong></div>
                                <div><span>QPS</span><strong>{{ qpsText(overview.redis_backup.qps) }}</strong></div>
                                <div><span>Clients</span><strong>{{ overview.redis_backup.clients ?? '-' }}</strong></div>
                                <div><span>Hit Rate</span><strong>{{ percentText(overview.redis_backup.hit_rate) }}</strong></div>
                                <div><span>Uptime</span><strong>{{ overview.redis_backup.uptime_days ?? '-' }} hari</strong></div>
                            </div>
                            <div class="redis-keyspace mt-3" v-if="overview.redis_backup.keyspace.length">
                                <div class="metric-label mb-2">Keyspace</div>
                                <div class="keyspace-row" v-for="db in overview.redis_backup.keyspace" :key="db.db">
                                    <span>{{ db.db }}</span>
                                    <strong>{{ db.keys }} keys</strong>
                                    <small>{{ db.expires }} exp</small>
                                </div>
                            </div>
                            <div class="overview-foot mt-3">Frag: {{ overview.redis_backup.memory.fragmentation ?? '-' }} | Max: {{ overview.redis_backup.memory.max }}</div>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <small class="text-muted">Update terakhir: {{ stats.updated_at || '-' }}</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white;">
                            <div class="card-body p-4 p-lg-5">
                                <div class="row align-items-center">
                                    <div class="col-lg-8 mb-3 mb-lg-0">
                                        <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-2 mb-3">
                                            <i class="fa-solid fa-tower-broadcast me-1"></i> Dedicated Live Feed
                                        </span>
                                        <h3 class="fw-bold mb-2">Radar Nilai Real-Time</h3>
                                        <p class="text-white text-opacity-80 mb-0">
                                            Kini dipindahkan ke halaman khusus untuk kenyamanan pemantauan yang lebih maksimal. 
                                            Pantau progres pengerjaan, live score, dan kelola sesi ujian siswa secara real-time.
                                        </p>
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <router-link to="/vue/monitoring/radar" class="btn btn-light text-primary fw-bold px-4 py-3 rounded-3 shadow">
                                            <i class="fa-solid fa-eye me-1"></i> Buka Radar Real-Time
                                        </router-link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const stats = ref({
    total_siswa: 0,
    ujian_berlangsung: 0,
    peserta_aktif: 0,
    siswa_selesai: 0,
    updated_at: '-'
});
const currentUser = ref({ name: 'Loading...', role: 'SuperAdmin' });
const emptyRedisOverview = (name = 'Redis') => ({
    name,
    host: '-',
    port: '-',
    status: 'unknown',
    memory: { used: '-', peak: '-', max: '-', fragmentation: null },
    qps: null,
    clients: null,
    hit_rate: null,
    uptime_days: null,
    keyspace: [],
    message: '-',
});
const overview = ref({
    main_server: {
        name: 'Server Utama',
        host: '-',
        status: 'unknown',
        load: { one: 0, five: 0, fifteen: 0 },
        memory: { used_mb: null, total_mb: null, percent: null },
        disk: { used_gb: null, total_gb: null, percent: null },
    },
    primary_db: { name: 'DB Utama', host: '-', port: '-', database: '-', status: 'unknown', role: '-', latency_ms: null, size: '-', connections: null, db_time: null, message: '-' },
    secondary_db: { name: 'Server 2 DB', host: '-', port: '-', database: '-', status: 'unknown', role: '-', latency_ms: null, size: '-', connections: null, db_time: null, message: '-' },
    redis_primary: emptyRedisOverview('Redis Primary'),
    redis_backup: emptyRedisOverview('Redis Backup'),
});

let intervalStats = null;

const fetchUser = async () => {
    try {
        const response = await axios.get('/auth/user', { headers: { 'Accept': 'application/json' } });
        if (response.data) {
            currentUser.value = response.data;
        }
    } catch (e) {
        console.error('Gagal mengambil data user', e);
    }
};

const fetchStats = async () => {
    try {
        const response = await axios.get('/monitoring/stats', { headers: { 'Accept': 'application/json' } });
        if (response.data) {
            stats.value = response.data;
            if (response.data.overview) {
                overview.value = response.data.overview;
            }
        }
    } catch (e) {
        console.error('Gagal mengambil data statistik', e);
    }
};

const overviewHealthy = computed(() =>
    overview.value.main_server.status === 'online'
    && overview.value.primary_db.status === 'online'
    && overview.value.secondary_db.status === 'online'
    && overview.value.redis_primary.status === 'online'
    && overview.value.redis_backup.status === 'online'
);

const statusClass = status => status === 'online'
    ? 'status-online'
    : status === 'offline'
        ? 'status-offline'
        : 'status-unknown';

const statusText = status => status === 'online' ? 'Online' : status === 'offline' ? 'Offline' : 'Unknown';
const percentText = value => value === null || value === undefined ? '-' : `${value}%`;
const percentWidth = value => `${Math.min(Math.max(Number(value || 0), 0), 100)}%`;
const sizeText = (value, unit) => value === null || value === undefined ? '-' : `${value} ${unit}`;
const latencyText = value => value === null || value === undefined ? '-' : `${value} ms`;
const qpsText = value => value === null || value === undefined ? '-' : `${value}/s`;

const logout = () => {
    Swal.fire({
        title: 'Keluar Sistem?',
        text: "Sesi SuperAdmin Anda akan ditutup.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/logout';
        }
    });
};

onMounted(() => {
    fetchUser();
    fetchStats();
    intervalStats = setInterval(fetchStats, 30000);
});

onUnmounted(() => {
    clearInterval(intervalStats);
});
</script>


<style scoped>
#wrapper {
    display: flex;
    width: 100vw;
    min-height: 100vh;
}
.sidebar {
    width: 280px;
    background-color: #111827; 
    color: #f8fafc;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.3s;
    z-index: 1030;
}
.sidebar-brand {
    padding: 1.5rem;
    background-color: #0f172a;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #1e293b;
}
.sidebar-brand img { width: 40px; margin-right: 15px; }
.sidebar-nav { padding: 1rem 0; flex-grow: 1; }
.nav-item-custom {
    padding: 0.8rem 1.5rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 4px solid transparent;
}
.nav-item-custom:hover {
    color: #f8fafc;
    background-color: #1e293b;
}
.nav-item-custom.active {
    color: #60a5fa;
    background-color: #1e293b;
    border-left: 4px solid #3b82f6;
    font-weight: 600;
}
.nav-item-custom i { width: 25px; font-size: 1.1rem; }
.main-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: 100vh;
    overflow-y: auto;
}
.top-navbar {
    background-color: #ffffff;
    height: 70px;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    position: sticky;
    top: 0;
    z-index: 1020;
}
.stat-card {
    background: #fff;
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    padding: 1.5rem;
    display: flex;
    align-items: center;
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-right: 1.2rem;
}
.icon-blue { background-color: #eff6ff; color: #3b82f6; }
.icon-green { background-color: #f0fdf4; color: #22c55e; }
.icon-purple { background-color: #faf5ff; color: #a855f7; }
.icon-red { background-color: #fef2f2; color: #ef4444; }
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    padding: 1rem 1.25rem;
}
.overview-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    padding: 1.25rem;
    min-height: 100%;
}
.overview-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.overview-title {
    font-weight: 700;
    color: #0f172a;
}
.overview-host {
    color: #64748b;
    font-size: 0.85rem;
}
.status-pill {
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    text-transform: uppercase;
}
.status-online {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}
.status-offline {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.status-unknown {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.metric-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.metric-grid > div,
.db-meta > div {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem;
}
.metric-label,
.db-meta span {
    display: block;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}
.metric-value,
.db-meta strong {
    color: #0f172a;
    font-size: 1.1rem;
}
.db-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}
.progress-row {
    margin-bottom: 0.85rem;
}
.redis-keyspace {
    border-top: 1px solid #e2e8f0;
    padding-top: 0.75rem;
}
.keyspace-row {
    display: grid;
    grid-template-columns: 0.8fr 1fr 0.8fr;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0;
    color: #475569;
    font-size: 0.82rem;
}
.keyspace-row strong {
    color: #0f172a;
}
.keyspace-row small {
    text-align: right;
}
.progress {
    height: 8px;
    background: #e2e8f0;
}
.overview-foot {
    margin-top: 0.4rem;
    color: #64748b;
    font-size: 0.82rem;
}
.bg-success-subtle { background-color: #dcfce7 !important; }
.bg-danger-subtle { background-color: #fee2e2 !important; }
.table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: none;
}
.table-custom thead th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
    padding: 1rem;
}
.table-custom tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}
.live-badge {
    background-color: #fee2e2;
    color: #dc2626;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    border: 1px solid #fca5a5;
}
.live-dot {
    width: 8px;
    height: 8px;
    background-color: #dc2626;
    border-radius: 50%;
    margin-right: 6px;
    animation: pulse 1.5s infinite;
}
@keyframes scoreUpdate {
    0% { background-color: transparent; }
    50% { background-color: #dcfce7; }
    100% { background-color: transparent; }
}
.score-updated {
    animation: scoreUpdate 1.5s ease;
}
.score-cell {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e3a8a;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(220, 38, 38, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}
</style>
