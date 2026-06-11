<template>
    <div id="wrapper">
        <AdminSidebar />

        <main class="main-content">
            <header class="top-navbar">
                <div>
                    <h5 class="mb-1 fw-bold text-dark">Dashboard SuperAdmin</h5>
                    <div class="text-muted small">Update terakhir: {{ stats.updated_at || '-' }}</div>
                </div>
                <div class="top-actions">
                    <span class="health-badge" :class="overviewHealthy ? 'healthy' : 'attention'">
                        <i class="fa-solid fa-circle"></i>
                        {{ overviewHealthy ? 'Sistem normal' : 'Perlu dicek' }}
                    </span>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </header>

            <div class="dashboard-shell">
                <section class="overview-band">
                    <div class="overview-copy">
                        <span class="eyebrow">CBT Enterprise Server</span>
                        <h2>Ringkasan operasional ujian</h2>
                        <p>{{ currentUser.name }} - {{ currentUser.role }}</p>
                    </div>
                    <div class="overview-status">
                        <div>
                            <span>Status Infrastruktur</span>
                            <strong>{{ overviewHealthy ? 'Online' : 'Perlu pengecekan' }}</strong>
                        </div>
                        <router-link to="/vue/monitoring/radar" class="btn btn-primary">
                            <i class="fa-solid fa-tower-broadcast me-1"></i> Radar
                        </router-link>
                    </div>
                </section>

                <section class="stat-grid">
                    <div v-for="card in statCards" :key="card.label" class="stat-card" :class="card.tone">
                        <div class="stat-icon"><i :class="card.icon"></i></div>
                        <div>
                            <span>{{ card.label }}</span>
                            <strong>{{ card.value }}</strong>
                            <small>{{ card.note }}</small>
                        </div>
                    </div>
                </section>

                <section class="dashboard-grid">
                    <div class="panel panel-main">
                        <div class="panel-header">
                            <div>
                                <h6>Infrastruktur</h6>
                                <small>Server, database, Redis, dan pool koneksi.</small>
                            </div>
                            <button class="btn btn-sm btn-light border" @click="fetchStats">
                                <i class="fa-solid fa-rotate me-1"></i> Refresh
                            </button>
                        </div>

                        <div class="health-grid">
                            <article v-for="item in healthCards" :key="item.key" class="health-card">
                                <div class="health-head">
                                    <div>
                                        <h6>{{ item.title }}</h6>
                                        <span>{{ item.subtitle }}</span>
                                    </div>
                                    <span class="status-pill" :class="statusClass(item.status)">{{ statusText(item.status) }}</span>
                                </div>

                                <div v-if="item.type === 'server'" class="server-metrics">
                                    <div>
                                        <span>Load 1m</span>
                                        <strong>{{ overview.main_server.load.one }}</strong>
                                    </div>
                                    <div>
                                        <span>Load 5m</span>
                                        <strong>{{ overview.main_server.load.five }}</strong>
                                    </div>
                                    <div>
                                        <span>Load 15m</span>
                                        <strong>{{ overview.main_server.load.fifteen }}</strong>
                                    </div>
                                </div>

                                <div v-if="item.type === 'server'" class="resource-stack">
                                    <div class="resource-row">
                                        <div>
                                            <span>Memory</span>
                                            <strong>{{ sizeText(overview.main_server.memory.used_mb, 'MB') }} / {{ sizeText(overview.main_server.memory.total_mb, 'MB') }}</strong>
                                        </div>
                                        <em>{{ percentText(overview.main_server.memory.percent) }}</em>
                                        <div class="progress"><div class="progress-bar memory" :style="{ width: percentWidth(overview.main_server.memory.percent) }"></div></div>
                                    </div>
                                    <div class="resource-row">
                                        <div>
                                            <span>Disk</span>
                                            <strong>{{ sizeText(overview.main_server.disk.used_gb, 'GB') }} / {{ sizeText(overview.main_server.disk.total_gb, 'GB') }}</strong>
                                        </div>
                                        <em>{{ percentText(overview.main_server.disk.percent) }}</em>
                                        <div class="progress"><div class="progress-bar disk" :style="{ width: percentWidth(overview.main_server.disk.percent) }"></div></div>
                                    </div>
                                </div>

                                <div v-else class="meta-grid">
                                    <div v-for="meta in item.meta" :key="meta.label">
                                        <span>{{ meta.label }}</span>
                                        <strong>{{ meta.value }}</strong>
                                    </div>
                                </div>

                                <div class="health-foot" :title="item.footer">{{ item.footer }}</div>
                            </article>
                        </div>
                    </div>

                    <aside class="side-stack">
                        <div class="panel">
                            <div class="panel-header compact">
                                <div>
                                    <h6>Aksi Cepat</h6>
                                    <small>Shortcut SuperAdmin.</small>
                                </div>
                            </div>
                            <div class="quick-list">
                                <router-link v-for="link in quickLinks" :key="link.to" :to="link.to" class="quick-link">
                                    <i :class="link.icon"></i>
                                    <span>{{ link.label }}</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </router-link>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header compact">
                                <div>
                                    <h6>Redis Keyspace</h6>
                                    <small>{{ overview.redis_primary.name }}</small>
                                </div>
                            </div>
                            <div v-if="overview.redis_primary.keyspace.length" class="keyspace-list">
                                <div v-for="db in overview.redis_primary.keyspace" :key="db.db" class="keyspace-row">
                                    <strong>{{ db.db }}</strong>
                                    <span>{{ db.keys }} keys</span>
                                    <small>{{ db.expires }} exp</small>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <i class="fa-solid fa-database"></i>
                                <span>Belum ada keyspace terbaca.</span>
                            </div>
                        </div>

                        <div class="radar-panel">
                            <div>
                                <span>Live Monitoring</span>
                                <h6>Radar Nilai Real-Time</h6>
                                <p>Pantau progres pengerjaan, skor berjalan, dan sesi siswa aktif.</p>
                            </div>
                            <router-link to="/vue/monitoring/radar" class="btn btn-light fw-bold">
                                <i class="fa-solid fa-eye me-1"></i> Buka Radar
                            </router-link>
                        </div>
                    </aside>
                </section>
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

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
const emptyPgbouncerOverview = () => ({
    name: 'PgBouncer',
    host: '-',
    port: '-',
    status: 'unknown',
    version: '-',
    database: '-',
    pool_mode: '-',
    clients_active: null,
    clients_waiting: null,
    servers_active: null,
    servers_idle: null,
    max_wait: null,
    avg_query_ms: null,
    avg_wait_ms: null,
    total_queries: null,
    total_xacts: null,
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
    pgbouncer: emptyPgbouncerOverview(),
});

let intervalStats = null;

const statCards = computed(() => [
    { label: 'Siswa Aktif', value: stats.value.total_siswa, note: 'Total akun siswa', icon: 'fa-solid fa-users', tone: 'blue' },
    { label: 'Ujian Berlangsung', value: stats.value.ujian_berlangsung, note: 'Jadwal aktif', icon: 'fa-solid fa-laptop-file', tone: 'green' },
    { label: 'Sesi Aktif', value: stats.value.peserta_aktif, note: 'Peserta sedang ujian', icon: 'fa-solid fa-user-check', tone: 'teal' },
    { label: 'Selesai', value: stats.value.siswa_selesai, note: 'Sesi selesai', icon: 'fa-solid fa-circle-check', tone: 'amber' },
]);

const healthCards = computed(() => [
    {
        key: 'server',
        type: 'server',
        title: overview.value.main_server.name,
        subtitle: overview.value.main_server.host,
        status: overview.value.main_server.status,
        footer: `Load: ${overview.value.main_server.load.one} / ${overview.value.main_server.load.five} / ${overview.value.main_server.load.fifteen}`,
    },
    {
        key: 'primary-db',
        title: overview.value.primary_db.name,
        subtitle: `${overview.value.primary_db.host}:${overview.value.primary_db.port}`,
        status: overview.value.primary_db.status,
        meta: [
            { label: 'Database', value: overview.value.primary_db.database },
            { label: 'Role', value: overview.value.primary_db.role },
            { label: 'Latency', value: latencyText(overview.value.primary_db.latency_ms) },
            { label: 'Size', value: overview.value.primary_db.size },
            { label: 'Conn', value: overview.value.primary_db.connections ?? '-' },
            { label: 'Time', value: overview.value.primary_db.db_time || '-' },
        ],
        footer: overview.value.primary_db.message || '-',
    },
    {
        key: 'secondary-db',
        title: overview.value.secondary_db.name,
        subtitle: `${overview.value.secondary_db.host}:${overview.value.secondary_db.port}`,
        status: overview.value.secondary_db.status,
        meta: [
            { label: 'Database', value: overview.value.secondary_db.database },
            { label: 'Role', value: overview.value.secondary_db.role },
            { label: 'Latency', value: latencyText(overview.value.secondary_db.latency_ms) },
            { label: 'Size', value: overview.value.secondary_db.size },
            { label: 'Conn', value: overview.value.secondary_db.connections ?? '-' },
            { label: 'Time', value: overview.value.secondary_db.db_time || '-' },
        ],
        footer: overview.value.secondary_db.message || '-',
    },
    {
        key: 'redis',
        title: overview.value.redis_primary.name,
        subtitle: `${overview.value.redis_primary.host}:${overview.value.redis_primary.port}`,
        status: overview.value.redis_primary.status,
        meta: [
            { label: 'Memory', value: overview.value.redis_primary.memory.used },
            { label: 'Peak', value: overview.value.redis_primary.memory.peak },
            { label: 'QPS', value: qpsText(overview.value.redis_primary.qps) },
            { label: 'Clients', value: overview.value.redis_primary.clients ?? '-' },
            { label: 'Hit Rate', value: percentText(overview.value.redis_primary.hit_rate) },
            { label: 'Uptime', value: `${overview.value.redis_primary.uptime_days ?? '-'} hari` },
        ],
        footer: `Frag: ${overview.value.redis_primary.memory.fragmentation ?? '-'} | Max: ${overview.value.redis_primary.memory.max}`,
    },
    {
        key: 'pgbouncer',
        title: overview.value.pgbouncer.name,
        subtitle: `${overview.value.pgbouncer.host}:${overview.value.pgbouncer.port}`,
        status: overview.value.pgbouncer.status,
        meta: [
            { label: 'Pool', value: overview.value.pgbouncer.pool_mode },
            { label: 'Client Aktif', value: overview.value.pgbouncer.clients_active ?? '-' },
            { label: 'Client Tunggu', value: overview.value.pgbouncer.clients_waiting ?? '-' },
            { label: 'Server Aktif', value: overview.value.pgbouncer.servers_active ?? '-' },
            { label: 'Avg Query', value: latencyText(overview.value.pgbouncer.avg_query_ms) },
            { label: 'Avg Wait', value: latencyText(overview.value.pgbouncer.avg_wait_ms) },
        ],
        footer: overview.value.pgbouncer.version || overview.value.pgbouncer.message || '-',
    },
]);

const quickLinks = [
    { to: '/vue/management/jadwal', icon: 'fa-solid fa-calendar-days', label: 'Jadwal Ujian' },
    { to: '/vue/management/soal', icon: 'fa-solid fa-file-circle-check', label: 'Bank Soal' },
    { to: '/vue/management/hasil', icon: 'fa-solid fa-square-poll-vertical', label: 'Hasil Ujian' },
    { to: '/vue/management/fingerprints', icon: 'fa-solid fa-fingerprint', label: 'Kunci Perangkat' },
    { to: '/vue/management/staff', icon: 'fa-solid fa-users-gear', label: 'Manajemen Staf' },
];

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
    && overview.value.pgbouncer.status === 'online'
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
        text: 'Sesi SuperAdmin Anda akan ditutup.',
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
    background: #eef2f7;
}
.main-content {
    flex: 1;
    min-width: 0;
    height: 100vh;
    overflow-y: auto;
    background:
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 36%, #eef2f7 100%);
}
.top-navbar {
    min-height: 72px;
    padding: 0.9rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: rgba(255,255,255,0.92);
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 1020;
    backdrop-filter: blur(12px);
}
.top-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.health-badge {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    border: 1px solid;
}
.health-badge i { font-size: 0.5rem; }
.health-badge.healthy { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.health-badge.attention { color: #b45309; background: #fffbeb; border-color: #fcd34d; }
.dashboard-shell {
    padding: 1.5rem;
}
.overview-band {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: stretch;
    padding: 1.25rem;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
}
.overview-copy {
    display: grid;
    gap: 0.25rem;
}
.eyebrow {
    color: #0f766e;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
}
.overview-copy h2 {
    margin: 0;
    color: #0f172a;
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: 0;
}
.overview-copy p {
    margin: 0;
    color: #64748b;
}
.overview-status {
    min-width: 270px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.overview-status span {
    display: block;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}
.overview-status strong {
    color: #0f172a;
}
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-height: 112px;
    padding: 1rem;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.035);
}
.stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
    flex: 0 0 auto;
}
.stat-card span, .stat-card small {
    display: block;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
}
.stat-card strong {
    display: block;
    color: #0f172a;
    font-size: 1.7rem;
    line-height: 1.1;
    margin: 0.2rem 0;
}
.stat-card small {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: none;
}
.stat-card.blue .stat-icon { color: #2563eb; background: #dbeafe; }
.stat-card.green .stat-icon { color: #15803d; background: #dcfce7; }
.stat-card.teal .stat-icon { color: #0f766e; background: #ccfbf1; }
.stat-card.amber .stat-icon { color: #b45309; background: #fef3c7; }
.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 1rem;
    align-items: start;
}
.panel {
    border-radius: 8px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.035);
}
.panel-main {
    padding: 1rem;
}
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}
.panel-header.compact {
    padding: 1rem 1rem 0;
    margin-bottom: 0.75rem;
}
.panel-header h6 {
    margin: 0;
    color: #0f172a;
    font-weight: 800;
}
.panel-header small {
    color: #64748b;
}
.health-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.health-card {
    min-width: 0;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fbfdff;
}
.health-card:first-child {
    grid-column: 1 / -1;
}
.health-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}
.health-head h6 {
    margin: 0;
    color: #0f172a;
    font-weight: 800;
}
.health-head span {
    color: #64748b;
    font-size: 0.82rem;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    border: 1px solid;
}
.status-online { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.status-offline { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.status-unknown { color: #475569; background: #f8fafc; border-color: #cbd5e1; }
.server-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.server-metrics div,
.meta-grid div {
    padding: 0.72rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.server-metrics span,
.meta-grid span {
    display: block;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}
.server-metrics strong,
.meta-grid strong {
    display: block;
    color: #0f172a;
    font-size: 1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.resource-stack {
    display: grid;
    gap: 0.75rem;
}
.resource-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.5rem;
    align-items: center;
}
.resource-row span {
    display: block;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
}
.resource-row strong {
    color: #0f172a;
}
.resource-row em {
    color: #334155;
    font-style: normal;
    font-weight: 800;
}
.progress {
    grid-column: 1 / -1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
}
.progress-bar.memory { background: #2563eb; }
.progress-bar.disk { background: #0f766e; }
.meta-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}
.health-foot {
    margin-top: 0.8rem;
    color: #64748b;
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.side-stack {
    display: grid;
    gap: 1rem;
    position: sticky;
    top: 88px;
}
.quick-list {
    display: grid;
    padding: 0 1rem 1rem;
}
.quick-link {
    display: grid;
    grid-template-columns: 26px minmax(0, 1fr) 14px;
    align-items: center;
    gap: 0.65rem;
    min-height: 44px;
    color: #334155;
    text-decoration: none;
    border-top: 1px solid #e2e8f0;
}
.quick-link:first-child {
    border-top: none;
}
.quick-link:hover {
    color: #0f766e;
}
.quick-link span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 700;
}
.keyspace-list {
    display: grid;
    gap: 0.5rem;
    padding: 0 1rem 1rem;
}
.keyspace-row {
    display: grid;
    grid-template-columns: 0.7fr 1fr 0.8fr;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.keyspace-row strong { color: #0f172a; }
.keyspace-row span { color: #334155; }
.keyspace-row small { color: #64748b; text-align: right; }
.empty-state {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin: 0 1rem 1rem;
    padding: 1rem;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    background: #f8fafc;
}
.radar-panel {
    display: grid;
    gap: 1rem;
    padding: 1.15rem;
    border-radius: 8px;
    background: #0f766e;
    color: #fff;
    box-shadow: 0 12px 30px rgba(15, 118, 110, 0.18);
}
.radar-panel span {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #ccfbf1;
}
.radar-panel h6 {
    margin: 0.15rem 0;
    font-weight: 800;
}
.radar-panel p {
    margin: 0;
    color: #d9fffa;
    font-size: 0.88rem;
}
@media (max-width: 1200px) {
    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .side-stack {
        position: static;
    }
}
@media (max-width: 768px) {
    #wrapper {
        display: block;
    }
    .main-content {
        height: auto;
        min-height: 100vh;
    }
    .top-navbar,
    .overview-band,
    .overview-status {
        flex-direction: column;
        align-items: stretch;
    }
    .dashboard-shell {
        padding: 1rem;
    }
    .stat-grid,
    .health-grid,
    .server-metrics,
    .meta-grid {
        grid-template-columns: 1fr;
    }
    .health-card:first-child {
        grid-column: auto;
    }
}
</style>
