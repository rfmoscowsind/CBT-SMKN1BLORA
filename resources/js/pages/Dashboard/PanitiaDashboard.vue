<template>
    <div id="wrapper">
        <AdminSidebar />

        <main class="main-content">
            <header class="top-navbar">
                <div>
                    <h5 class="mb-1 fw-bold text-dark">Dashboard Panitia</h5>
                    <div class="text-muted small">{{ currentUser.name }} - {{ currentUser.role }}</div>
                </div>
                <div class="top-actions">
                    <router-link to="/vue/management/jadwal" class="btn btn-primary btn-sm px-3">
                        <i class="fa-solid fa-calendar-plus me-1"></i> Jadwal
                    </router-link>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </header>

            <div class="dashboard-shell">
                <section class="summary-band">
                    <div>
                        <span class="eyebrow">Operasional CBT</span>
                        <h2>Kontrol ujian hari ini</h2>
                        <p>Reset sesi, cek jadwal aktif, dan unduh hasil dari satu halaman ringkas.</p>
                    </div>
                    <button class="btn btn-light border fw-semibold" @click="fetchDashboardData">
                        <i class="fa-solid fa-rotate me-1"></i> Refresh
                    </button>
                </section>

                <section class="stat-grid">
                    <div v-for="item in statCards" :key="item.label" class="stat-card" :class="item.tone">
                        <div class="stat-icon"><i :class="item.icon"></i></div>
                        <div>
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                            <small>{{ item.note }}</small>
                        </div>
                    </div>
                </section>

                <section class="content-grid">
                    <article class="panel">
                        <div class="panel-header">
                            <div>
                                <h6>Butuh Reset Sesi</h6>
                                <small>Sesi aktif atau terkunci yang perlu dibantu panitia.</small>
                            </div>
                            <span class="count-pill danger">{{ sesiBermasalah.length }}</span>
                        </div>

                        <div v-if="sesiBermasalah.length === 0" class="empty-state">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Tidak ada sesi bermasalah.</span>
                        </div>
                        <div v-else class="list-stack">
                            <div v-for="siswa in sesiBermasalah.slice(0, 8)" :key="siswa.id" class="data-row">
                                <div class="row-icon danger"><i class="fa-solid fa-rotate-left"></i></div>
                                <div class="row-main">
                                    <strong>{{ siswa.name }}</strong>
                                    <span>{{ siswa.kelas }} - {{ siswa.mapel }}</span>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" @click="resetSesi(siswa)">Reset</button>
                            </div>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel-header">
                            <div>
                                <h6>Siap Download</h6>
                                <small>Ringkasan ujian selesai yang sudah bisa diekspor.</small>
                            </div>
                            <router-link to="/vue/management/download-hasil" class="btn btn-sm btn-light border">Buka</router-link>
                        </div>

                        <div v-if="ujianSelesai.length === 0" class="empty-state">
                            <i class="fa-solid fa-file-circle-check"></i>
                            <span>Belum ada hasil siap unduh.</span>
                        </div>
                        <div v-else class="list-stack">
                            <div v-for="hasil in ujianSelesai.slice(0, 8)" :key="hasil.id" class="data-row">
                                <div class="row-icon success"><i class="fa-solid fa-file-export"></i></div>
                                <div class="row-main">
                                    <strong>{{ hasil.mapel }}</strong>
                                    <span>{{ hasil.tanggal }} - {{ hasil.jumlah_peserta }} siswa</span>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" @click="exportData(hasil.id, 'pdf')">PDF</button>
                            </div>
                        </div>
                    </article>

                    <article class="panel quick-panel">
                        <div class="panel-header">
                            <div>
                                <h6>Aksi Cepat</h6>
                                <small>Menu yang paling sering dipakai panitia.</small>
                            </div>
                        </div>
                        <div class="quick-grid">
                            <router-link to="/vue/management/siswa" class="quick-card">
                                <i class="fa-solid fa-users"></i>
                                <span>Siswa</span>
                            </router-link>
                            <router-link to="/vue/management/jadwal" class="quick-card">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span>Jadwal</span>
                            </router-link>
                            <router-link to="/vue/management/soal" class="quick-card">
                                <i class="fa-solid fa-file-circle-check"></i>
                                <span>Bank Soal</span>
                            </router-link>
                            <router-link to="/vue/management/hasil" class="quick-card">
                                <i class="fa-solid fa-square-poll-vertical"></i>
                                <span>Hasil</span>
                            </router-link>
                        </div>
                    </article>
                </section>
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AdminSidebar from '../../components/AdminSidebar.vue';

const sesiBermasalah = ref([]);
const ujianSelesai = ref([]);
const stats = ref({
    total_siswa: 0,
    ujian_berlangsung: 0,
    peserta_aktif: 0,
    total_paket: 0
});
const currentUser = ref({ name: 'Loading...', role: 'Panitia' });

let intervalId = null;

const statCards = computed(() => [
    { label: 'Siswa Aktif', value: stats.value.total_siswa, note: 'Akun siswa', icon: 'fa-solid fa-user-graduate', tone: 'blue' },
    { label: 'Jadwal Aktif', value: stats.value.ujian_berlangsung, note: 'Sesi hari ini', icon: 'fa-solid fa-calendar-day', tone: 'green' },
    { label: 'Paket Soal', value: stats.value.total_paket || 0, note: 'Bank siap pakai', icon: 'fa-solid fa-file-circle-check', tone: 'teal' },
    { label: 'Reset Sesi', value: sesiBermasalah.value.length, note: 'Perlu perhatian', icon: 'fa-solid fa-triangle-exclamation', tone: 'amber' },
]);

const fetchUser = async () => {
    try {
        const response = await axios.get('/auth/user', { headers: { 'Accept': 'application/json' } });
        if (response.data) currentUser.value = response.data;
    } catch (e) {
        console.error('Gagal mengambil data user', e);
    }
};

const fetchDashboardData = async () => {
    try {
        const resStats = await axios.get('/monitoring/stats', { headers: { 'Accept': 'application/json' } });
        if (resStats.data) stats.value = resStats.data;

        const resSessions = await axios.get('/monitoring/sessions', { headers: { 'Accept': 'application/json' } });
        if (resSessions.data && Array.isArray(resSessions.data)) {
            sesiBermasalah.value = resSessions.data.filter(s => ['aktif', 'terkunci'].includes(s.status));
            const finished = resSessions.data.filter(s => s.status === 'selesai');
            const uniqueFinished = [];
            const seenMapel = new Set();
            finished.forEach(s => {
                if (!seenMapel.has(s.mapel)) {
                    seenMapel.add(s.mapel);
                    uniqueFinished.push({
                        id: s.jadwal_ujian_id,
                        mapel: s.mapel,
                        tanggal: s.waktu_submit ? new Date(s.waktu_submit).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-',
                        jumlah_peserta: finished.filter(f => f.mapel === s.mapel).length
                    });
                }
            });
            ujianSelesai.value = uniqueFinished;
        }
    } catch (e) {
        console.error('Gagal memuat data dashboard panitia', e);
    }
};

const resetSesi = (siswa) => {
    Swal.fire({
        title: 'Reset Sesi Ujian?',
        html: `Reset sesi <b>${siswa.name} (${siswa.kelas})</b>. Jawaban sebelumnya tetap aman.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Reset Sekarang',
        cancelButtonText: 'Batal'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await axios.post(`/kelola/sesi/${siswa.id}/reset`, {}, { headers: { 'Accept': 'application/json' } });
                if (response.data && response.data.success) {
                    Swal.fire('Berhasil', 'Siswa kini dapat login kembali.', 'success');
                    fetchDashboardData();
                }
            } catch (e) {
                Swal.fire('Error', 'Gagal mereset sesi siswa.', 'error');
            }
        }
    });
};

const exportData = (jadwal_id, tipe) => {
    if (!jadwal_id) {
        window.location.href = '/vue/management/download-hasil';
        return;
    }
    const format = tipe === 'excel' ? 'xlsx' : 'pdf';
    window.location.href = `/kelola/laporan/${jadwal_id}/${format}`;
};

const logout = () => {
    Swal.fire({
        title: 'Keluar Sistem?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = '/logout';
    });
};

onMounted(() => {
    fetchUser();
    fetchDashboardData();
    intervalId = setInterval(fetchDashboardData, 5000);
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; background: #eef2f7; }
.main-content { flex: 1; min-width: 0; height: 100vh; overflow-y: auto; background: #eef2f7; }
.top-navbar { min-height: 72px; padding: 0.9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(255,255,255,0.92); border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1020; backdrop-filter: blur(12px); }
.top-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; justify-content: flex-end; }
.dashboard-shell { padding: 1.5rem; }
.summary-band { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem; border-radius: 8px; border: 1px solid #dbe4ef; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,0.04); }
.eyebrow { color: #0f766e; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.summary-band h2 { margin: 0.15rem 0; color: #0f172a; font-size: 1.55rem; font-weight: 800; letter-spacing: 0; }
.summary-band p { margin: 0; color: #64748b; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin: 1rem 0; }
.stat-card { min-height: 108px; display: flex; align-items: center; gap: 0.85rem; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 10px 24px rgba(15,23,42,0.035); }
.stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex: 0 0 auto; }
.stat-card span, .stat-card small { display: block; color: #64748b; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; }
.stat-card strong { display: block; color: #0f172a; font-size: 1.65rem; line-height: 1.1; margin: 0.2rem 0; }
.stat-card small { font-weight: 600; text-transform: none; }
.stat-card.blue .stat-icon { color: #2563eb; background: #dbeafe; }
.stat-card.green .stat-icon { color: #15803d; background: #dcfce7; }
.stat-card.teal .stat-icon { color: #0f766e; background: #ccfbf1; }
.stat-card.amber .stat-icon { color: #b45309; background: #fef3c7; }
.content-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; align-items: start; }
.panel { border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 24px rgba(15,23,42,0.035); padding: 1rem; min-width: 0; }
.quick-panel { grid-column: 1 / -1; }
.panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.panel-header h6 { margin: 0; color: #0f172a; font-weight: 800; }
.panel-header small { color: #64748b; }
.count-pill { min-width: 34px; min-height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 800; }
.count-pill.danger { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.empty-state { display: flex; align-items: center; gap: 0.7rem; min-height: 96px; padding: 1rem; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; background: #f8fafc; }
.list-stack { display: grid; gap: 0.65rem; }
.data-row { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fbfdff; }
.row-icon { width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.row-icon.danger { color: #b91c1c; background: #fee2e2; }
.row-icon.success { color: #15803d; background: #dcfce7; }
.row-main { min-width: 0; display: grid; }
.row-main strong, .row-main span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-main strong { color: #0f172a; }
.row-main span { color: #64748b; font-size: 0.84rem; }
.quick-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem; }
.quick-card { display: flex; align-items: center; gap: 0.65rem; min-height: 54px; padding: 0.8rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; color: #334155; text-decoration: none; font-weight: 800; }
.quick-card:hover { color: #0f766e; border-color: #99f6e4; background: #f0fdfa; }
@media (max-width: 1100px) { .stat-grid, .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .content-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { #wrapper { display: block; } .main-content { height: auto; min-height: 100vh; } .top-navbar, .summary-band { flex-direction: column; align-items: stretch; } .dashboard-shell { padding: 1rem; } .stat-grid, .quick-grid { grid-template-columns: 1fr; } .data-row { grid-template-columns: 38px minmax(0, 1fr); } .data-row .btn { grid-column: 1 / -1; width: 100%; } }
</style>
