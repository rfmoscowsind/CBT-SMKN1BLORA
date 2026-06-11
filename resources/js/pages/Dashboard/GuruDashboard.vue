<template>
  <div class="teacher-page">
    <aside class="teacher-sidebar">
      <div class="brand">
        <i class="fa-solid fa-graduation-cap"></i>
        <div>
          <strong>CBT Guru</strong>
          <span>Panel pengajar</span>
        </div>
      </div>
      <router-link v-for="item in menuItems" :key="item.to" :to="item.to" class="nav-link" :class="{ active: route.path === item.to }">
        <i :class="item.icon"></i>
        <span>{{ item.label }}</span>
      </router-link>
      <button class="nav-link logout" @click="doLogout">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Keluar</span>
      </button>
    </aside>

    <main class="teacher-main">
      <header class="topbar">
        <div>
          <h5 class="mb-1 fw-bold">Dashboard Guru</h5>
          <div class="text-muted small">Selamat datang, {{ userName || 'Guru' }}</div>
        </div>
        <div class="top-actions">
          <span class="time-pill"><i class="fa-regular fa-clock me-1"></i>{{ currentTime }}</span>
          <router-link to="/vue/management/soal" class="btn btn-primary btn-sm px-3">
            <i class="fa-solid fa-plus me-1"></i> Paket Soal
          </router-link>
        </div>
      </header>

      <section class="dashboard-shell">
        <div class="summary-band">
          <div>
            <span class="eyebrow">Akademik Ujian</span>
            <h2>Materi, jadwal, dan penilaian</h2>
            <p>Kelola paket soal dan tuntaskan jawaban essay yang menunggu penilaian.</p>
          </div>
          <router-link to="/vue/management/hasil" class="btn btn-light border fw-semibold">
            <i class="fa-solid fa-pen-to-square me-1"></i> Penilaian
          </router-link>
        </div>

        <div class="stat-grid" v-if="!loadingStats">
          <div v-for="item in statCards" :key="item.label" class="stat-card" :class="item.tone">
            <div class="stat-icon"><i :class="item.icon"></i></div>
            <div>
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
              <small>{{ item.note }}</small>
            </div>
          </div>
        </div>
        <div class="stat-grid" v-else>
          <div class="stat-card skeleton" v-for="i in 4" :key="i"></div>
        </div>

        <div class="content-grid">
          <article class="panel">
            <div class="panel-header">
              <div>
                <h6>Paket Soal Saya</h6>
                <small>Paket terbaru dan status kesiapan.</small>
              </div>
              <router-link to="/vue/management/soal" class="btn btn-sm btn-light border">Kelola</router-link>
            </div>
            <div v-if="loadingPaket" class="loading-state"><span class="spinner-border spinner-border-sm"></span></div>
            <div v-else-if="paketList.length === 0" class="empty-state">
              <i class="fa-regular fa-folder-open"></i>
              <span>Belum ada paket soal.</span>
            </div>
            <div v-else class="list-stack">
              <div v-for="p in paketList.slice(0, 8)" :key="p.id" class="data-row">
                <div class="row-icon blue"><i class="fa-solid fa-book-open"></i></div>
                <div class="row-main">
                  <strong>{{ p.judul }}</strong>
                  <span>{{ p.nama_mapel }} - {{ p.jumlah_soal }} soal</span>
                </div>
                <span class="status-pill" :class="p.status === 'ready' ? 'ready' : 'draft'">{{ p.status === 'ready' ? 'Siap' : 'Draft' }}</span>
              </div>
            </div>
          </article>

          <article class="panel">
            <div class="panel-header">
              <div>
                <h6>Jadwal Terkait</h6>
                <small>Jadwal yang memakai paket soal Anda.</small>
              </div>
              <router-link to="/vue/management/jadwal" class="btn btn-sm btn-light border">Lihat</router-link>
            </div>
            <div v-if="loadingJadwal" class="loading-state"><span class="spinner-border spinner-border-sm"></span></div>
            <div v-else-if="jadwalList.length === 0" class="empty-state">
              <i class="fa-regular fa-calendar-xmark"></i>
              <span>Tidak ada jadwal terkait.</span>
            </div>
            <div v-else class="list-stack">
              <div v-for="j in jadwalList.slice(0, 8)" :key="j.id" class="data-row">
                <div class="row-icon green"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="row-main">
                  <strong>{{ j.judul }}</strong>
                  <span>{{ j.nama_mapel }} - {{ j.waktu_mulai }}</span>
                </div>
                <span class="status-pill" :class="statusClass(j)">{{ statusLabel(j) }}</span>
              </div>
            </div>
          </article>

          <article class="panel full">
            <div class="panel-header">
              <div>
                <h6>Antrian Penilaian Essay</h6>
                <small>{{ pendingList.length }} jawaban menunggu penilaian manual.</small>
              </div>
              <router-link to="/vue/management/hasil" class="btn btn-sm btn-danger">Mulai Nilai</router-link>
            </div>
            <div v-if="loadingGrading" class="loading-state"><span class="spinner-border spinner-border-sm"></span></div>
            <div v-else-if="pendingList.length === 0" class="empty-state">
              <i class="fa-solid fa-circle-check"></i>
              <span>Semua jawaban essay sudah dinilai.</span>
            </div>
            <div v-else class="essay-grid">
              <div v-for="(item, idx) in pendingList.slice(0, 6)" :key="item.id" class="essay-card">
                <span>#{{ idx + 1 }}</span>
                <strong>{{ item.pertanyaan }}</strong>
                <p>{{ item.jawaban_essay || '-' }}</p>
                <div>
                  <small>Bobot {{ item.bobot_nilai }}</small>
                  <router-link to="/vue/management/hasil" class="btn btn-sm btn-outline-danger">Nilai</router-link>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Swal from 'sweetalert2';

const route = useRoute();

const userName = ref('');
const currentTime = ref('');
const stats = ref({ total_paket: 0, total_soal: 0, jadwal_aktif: 0, pending_grading: 0 });
const paketList = ref([]);
const jadwalList = ref([]);
const pendingList = ref([]);

const loadingStats = ref(true);
const loadingPaket = ref(true);
const loadingJadwal = ref(true);
const loadingGrading = ref(true);

let clockTimer = null;

const menuItems = [
  { to: '/vue/dashboard/guru', icon: 'fa-solid fa-gauge', label: 'Dashboard' },
  { to: '/vue/management/soal', icon: 'fa-solid fa-book-open', label: 'Bank Soal' },
  { to: '/vue/management/hasil', icon: 'fa-solid fa-pen-to-square', label: 'Penilaian' },
  { to: '/vue/management/jadwal', icon: 'fa-solid fa-calendar-days', label: 'Jadwal' },
];

const statCards = computed(() => [
  { label: 'Paket Soal', value: stats.value.total_paket, note: 'Dibuat guru', icon: 'fa-solid fa-book-open', tone: 'blue' },
  { label: 'Total Soal', value: stats.value.total_soal, note: 'Butir tersedia', icon: 'fa-solid fa-circle-question', tone: 'green' },
  { label: 'Ujian Aktif', value: stats.value.jadwal_aktif, note: 'Hari ini', icon: 'fa-solid fa-calendar-check', tone: 'teal' },
  { label: 'Perlu Nilai', value: stats.value.pending_grading, note: 'Jawaban essay', icon: 'fa-solid fa-pen-to-square', tone: 'red' },
]);

const updateClock = () => {
  currentTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const fetchUser = async () => {
  try {
    const { data } = await axios.get('/auth/user');
    userName.value = data.name;
  } catch {
    userName.value = 'Guru';
  }
};

const fetchPaket = async () => {
  loadingPaket.value = true;
  try {
    const { data } = await axios.get('/api/v1/guru/paket-soal');
    paketList.value = data.data || [];
    stats.value.total_paket = paketList.value.length;
    stats.value.total_soal = paketList.value.reduce((sum, paket) => sum + (paket.jumlah_soal || 0), 0);
  } catch {
    paketList.value = [];
  } finally {
    loadingPaket.value = false;
    loadingStats.value = false;
  }
};

const fetchJadwal = async () => {
  loadingJadwal.value = true;
  try {
    const { data } = await axios.get('/kelola/guru/jadwal-terkait');
    jadwalList.value = data.data || [];
    const today = new Date().toISOString().slice(0, 10);
    stats.value.jadwal_aktif = jadwalList.value.filter(j => j.waktu_mulai?.startsWith(today)).length;
  } catch {
    jadwalList.value = [];
  } finally {
    loadingJadwal.value = false;
  }
};

const fetchPending = async () => {
  loadingGrading.value = true;
  try {
    const { data } = await axios.get('/api/v1/grading/isian');
    pendingList.value = data.data || [];
    stats.value.pending_grading = pendingList.value.length;
  } catch {
    pendingList.value = [];
  } finally {
    loadingGrading.value = false;
  }
};

const statusClass = (jadwal) => {
  const now = new Date();
  const mulai = new Date(jadwal.waktu_mulai_raw);
  const selesai = new Date(jadwal.waktu_selesai_raw);
  if (now >= mulai && now <= selesai) return 'active';
  if (now < mulai) return 'upcoming';
  return 'done';
};

const statusLabel = (jadwal) => {
  const now = new Date();
  const mulai = new Date(jadwal.waktu_mulai_raw);
  const selesai = new Date(jadwal.waktu_selesai_raw);
  if (now >= mulai && now <= selesai) return 'Berlangsung';
  if (now < mulai) return 'Akan Datang';
  return 'Selesai';
};

const doLogout = async () => {
  const result = await Swal.fire({
    title: 'Keluar?',
    text: 'Sesi Anda akan ditutup.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Logout',
    cancelButtonText: 'Batal'
  });
  if (result.isConfirmed) window.location.href = '/logout';
};

onMounted(() => {
  updateClock();
  clockTimer = setInterval(updateClock, 1000);
  fetchUser();
  fetchPaket();
  fetchJadwal();
  fetchPending();
});

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer);
});
</script>

<style scoped>
.teacher-page { display: flex; min-height: 100vh; background: #eef2f7; color: #0f172a; }
.teacher-sidebar { width: 250px; min-height: 100vh; position: sticky; top: 0; display: flex; flex-direction: column; gap: 0.35rem; padding: 1rem 0.75rem; background: #111827; color: #f8fafc; }
.brand { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; margin-bottom: 0.5rem; border-bottom: 1px solid #1e293b; }
.brand i { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #1e3a8a; }
.brand strong, .brand span { display: block; }
.brand span { color: #94a3b8; font-size: 0.76rem; }
.nav-link { min-height: 42px; display: flex; align-items: center; gap: 0.7rem; padding: 0.65rem 0.75rem; color: #94a3b8; border-radius: 8px; text-decoration: none; border: 0; background: transparent; text-align: left; }
.nav-link:hover, .nav-link.active { color: #fff; background: #1e293b; }
.nav-link i { width: 22px; }
.nav-link.logout { margin-top: auto; color: #fecaca; }
.teacher-main { flex: 1; min-width: 0; height: 100vh; overflow-y: auto; }
.topbar { min-height: 72px; padding: 0.9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(255,255,255,0.94); border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1020; backdrop-filter: blur(12px); }
.top-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.time-pill { min-height: 34px; display: inline-flex; align-items: center; padding: 0.35rem 0.7rem; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-weight: 800; }
.dashboard-shell { padding: 1.5rem; }
.summary-band { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem; border: 1px solid #dbe4ef; border-radius: 8px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,0.04); }
.eyebrow { color: #0f766e; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.summary-band h2 { margin: 0.15rem 0; font-size: 1.55rem; font-weight: 800; letter-spacing: 0; }
.summary-band p { margin: 0; color: #64748b; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin: 1rem 0; }
.stat-card { min-height: 108px; display: flex; align-items: center; gap: 0.85rem; padding: 1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 24px rgba(15,23,42,0.035); }
.stat-card.skeleton { min-height: 108px; background: #f8fafc; }
.stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.stat-card span, .stat-card small { display: block; color: #64748b; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.stat-card strong { display: block; color: #0f172a; font-size: 1.65rem; line-height: 1.1; margin: 0.2rem 0; }
.stat-card small { text-transform: none; font-weight: 600; }
.stat-card.blue .stat-icon { color: #2563eb; background: #dbeafe; }
.stat-card.green .stat-icon { color: #15803d; background: #dcfce7; }
.stat-card.teal .stat-icon { color: #0f766e; background: #ccfbf1; }
.stat-card.red .stat-icon { color: #b91c1c; background: #fee2e2; }
.content-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.panel { min-width: 0; padding: 1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 24px rgba(15,23,42,0.035); }
.panel.full { grid-column: 1 / -1; }
.panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.panel-header h6 { margin: 0; font-weight: 800; }
.panel-header small { color: #64748b; }
.loading-state, .empty-state { min-height: 110px; display: flex; align-items: center; justify-content: center; gap: 0.7rem; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
.list-stack { display: grid; gap: 0.65rem; }
.data-row { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fbfdff; }
.row-icon { width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.row-icon.blue { color: #2563eb; background: #dbeafe; }
.row-icon.green { color: #15803d; background: #dcfce7; }
.row-main { min-width: 0; display: grid; }
.row-main strong, .row-main span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-main strong { color: #0f172a; }
.row-main span { color: #64748b; font-size: 0.84rem; }
.status-pill { min-height: 28px; display: inline-flex; align-items: center; justify-content: center; padding: 0.25rem 0.55rem; border-radius: 8px; font-size: 0.74rem; font-weight: 800; border: 1px solid; white-space: nowrap; }
.status-pill.ready, .status-pill.done { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.status-pill.draft, .status-pill.upcoming { color: #b45309; background: #fffbeb; border-color: #fcd34d; }
.status-pill.active { color: #0f766e; background: #f0fdfa; border-color: #99f6e4; }
.essay-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; }
.essay-card { display: grid; gap: 0.4rem; padding: 0.85rem; border-radius: 8px; border: 1px solid #e2e8f0; background: #fbfdff; }
.essay-card span { color: #64748b; font-size: 0.76rem; font-weight: 800; }
.essay-card strong { color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.essay-card p { min-height: 42px; margin: 0; color: #475569; font-size: 0.86rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.essay-card div { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
@media (max-width: 1100px) { .stat-grid, .content-grid, .essay-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .panel.full { grid-column: auto; } }
@media (max-width: 780px) { .teacher-page { display: block; } .teacher-sidebar { width: 100%; min-height: auto; position: static; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); } .brand { grid-column: 1 / -1; } .teacher-main { height: auto; min-height: 100vh; } .topbar, .summary-band { flex-direction: column; align-items: stretch; } .dashboard-shell { padding: 1rem; } .stat-grid, .content-grid, .essay-grid { grid-template-columns: 1fr; } .data-row { grid-template-columns: 42px minmax(0, 1fr); } .data-row .status-pill { grid-column: 1 / -1; justify-content: flex-start; } }
</style>
