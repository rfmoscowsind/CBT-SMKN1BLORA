<template>
  <div id="guru-wrapper">
    <!-- Sidebar -->
    <nav class="guru-sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-brand">
        <i class="fa-solid fa-graduation-cap"></i>
        <span class="brand-text">CBT Guru</span>
      </div>
      <ul class="sidebar-menu">
        <li v-for="item in menuItems" :key="item.to"
            :class="{ active: $route.path === item.to }"
            @click="$router.push(item.to)">
          <i :class="item.icon"></i>
          <span>{{ item.label }}</span>
        </li>
        <li class="menu-divider"></li>
        <li @click="doLogout" class="logout-item">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span>Keluar</span>
        </li>
      </ul>
    </nav>

    <!-- Main -->
    <div class="guru-main">
      <!-- Topbar -->
      <header class="guru-topbar">
        <button class="btn-toggle-sidebar" @click="sidebarCollapsed = !sidebarCollapsed">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-greeting">
          <span class="greeting-text">Selamat datang, <strong>{{ userName }}</strong></span>
        </div>
        <div class="topbar-right">
          <span class="badge-role"><i class="fa-solid fa-chalkboard-user me-1"></i>Guru</span>
          <div class="topbar-clock">{{ currentTime }}</div>
        </div>
      </header>

      <!-- Content -->
      <main class="guru-content">
        <!-- Stat Cards -->
        <div class="stats-grid" v-if="!loadingStats">
          <div class="stat-card blue">
            <div class="stat-icon"><i class="fa-solid fa-book-open"></i></div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.total_paket }}</div>
              <div class="stat-label">Paket Soal</div>
            </div>
          </div>
          <div class="stat-card green">
            <div class="stat-icon"><i class="fa-solid fa-circle-question"></i></div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.total_soal }}</div>
              <div class="stat-label">Total Soal</div>
            </div>
          </div>
          <div class="stat-card amber">
            <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.jadwal_aktif }}</div>
              <div class="stat-label">Ujian Aktif Hari Ini</div>
            </div>
          </div>
          <div class="stat-card red">
            <div class="stat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.pending_grading }}</div>
              <div class="stat-label">Menunggu Penilaian</div>
            </div>
          </div>
        </div>
        <div class="stats-grid" v-else>
          <div class="stat-card skeleton" v-for="i in 4" :key="i"></div>
        </div>

        <!-- 2-column layout -->
        <div class="guru-grid">
          <!-- Paket Soal Saya -->
          <div class="guru-card">
            <div class="card-head">
              <h6><i class="fa-solid fa-book me-2 text-primary"></i>Paket Soal Saya</h6>
              <router-link to="/vue/management/soal" class="btn-link-sm">Kelola &rarr;</router-link>
            </div>
            <div v-if="loadingPaket" class="loading-state">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            </div>
            <div v-else-if="paketList.length === 0" class="empty-state">
              <i class="fa-regular fa-folder-open fa-2x mb-2 text-muted"></i>
              <p class="text-muted mb-0">Belum ada paket soal</p>
              <router-link to="/vue/management/soal" class="btn btn-sm btn-primary mt-2">Buat Paket</router-link>
            </div>
            <ul class="paket-list" v-else>
              <li v-for="p in paketList" :key="p.id" class="paket-item">
                <div class="paket-info">
                  <span class="paket-name">{{ p.judul }}</span>
                  <span class="paket-meta">{{ p.nama_mapel }}</span>
                </div>
                <div class="paket-badges">
                  <span class="badge-count">{{ p.jumlah_soal }} soal</span>
                  <span class="badge-status" :class="p.status === 'ready' ? 'ready' : 'draft'">
                    {{ p.status === 'ready' ? 'Siap' : 'Draft' }}
                  </span>
                </div>
              </li>
            </ul>
          </div>

          <!-- Jadwal Ujian Terkait -->
          <div class="guru-card">
            <div class="card-head">
              <h6><i class="fa-solid fa-calendar-days me-2 text-amber"></i>Jadwal Ujian Terkait</h6>
              <router-link to="/vue/management/jadwal" class="btn-link-sm">Lihat Semua &rarr;</router-link>
            </div>
            <div v-if="loadingJadwal" class="loading-state">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            </div>
            <div v-else-if="jadwalList.length === 0" class="empty-state">
              <i class="fa-regular fa-calendar-xmark fa-2x mb-2 text-muted"></i>
              <p class="text-muted mb-0">Tidak ada jadwal terkait paket soal Anda</p>
            </div>
            <ul class="jadwal-list" v-else>
              <li v-for="j in jadwalList" :key="j.id" class="jadwal-item">
                <div class="jadwal-info">
                  <span class="jadwal-name">{{ j.judul }}</span>
                  <span class="jadwal-meta">{{ j.nama_mapel }} &bull; {{ j.waktu_mulai }}</span>
                </div>
                <span class="jadwal-status" :class="statusClass(j)">{{ statusLabel(j) }}</span>
              </li>
            </ul>
          </div>

          <!-- Antrian Penilaian Essay -->
          <div class="guru-card full-width">
            <div class="card-head">
              <h6><i class="fa-solid fa-pen-to-square me-2 text-red"></i>Antrian Penilaian Essay</h6>
              <router-link to="/vue/management/hasil" class="btn btn-sm btn-danger rounded-pill px-3">
                Mulai Nilai <i class="fa-solid fa-arrow-right ms-1"></i>
              </router-link>
            </div>
            <div v-if="loadingGrading" class="loading-state">
              <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
            </div>
            <div v-else-if="pendingList.length === 0" class="empty-state py-4">
              <i class="fa-solid fa-circle-check fa-2x mb-2 text-success"></i>
              <p class="text-muted mb-0">Semua jawaban essay sudah dinilai 🎉</p>
            </div>
            <div v-else>
              <div class="grading-alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>{{ pendingList.length }} jawaban</strong> menunggu penilaian manual
              </div>
              <div class="table-responsive">
                <table class="table-guru">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Pertanyaan</th>
                      <th>Jawaban Siswa</th>
                      <th>Bobot</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in pendingList.slice(0, 5)" :key="item.id">
                      <td>{{ idx + 1 }}</td>
                      <td class="text-truncate-cell">{{ item.pertanyaan }}</td>
                      <td class="text-truncate-cell essay-answer">{{ item.jawaban_essay || '—' }}</td>
                      <td><span class="badge-bobot">{{ item.bobot_nilai }}</span></td>
                      <td>
                        <router-link to="/vue/management/hasil" class="btn-grade">
                          <i class="fa-solid fa-pen"></i> Nilai
                        </router-link>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <p v-if="pendingList.length > 5" class="text-muted text-center small mt-2">
                  +{{ pendingList.length - 5 }} jawaban lainnya di halaman penilaian
                </p>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import Swal from 'sweetalert2';

const router = useRouter();
const route  = useRoute();

// ── State ──────────────────────────────────────────────────────────
const userName        = ref('');
const sidebarCollapsed = ref(false);
const currentTime     = ref('');
const stats           = ref({ total_paket: 0, total_soal: 0, jadwal_aktif: 0, pending_grading: 0 });
const paketList       = ref([]);
const jadwalList      = ref([]);
const pendingList     = ref([]);

const loadingStats    = ref(true);
const loadingPaket    = ref(true);
const loadingJadwal   = ref(true);
const loadingGrading  = ref(true);

let clockTimer = null;

// ── Menu ───────────────────────────────────────────────────────────
const menuItems = [
  { to: '/vue/dashboard/guru',      icon: 'fa-solid fa-gauge',           label: 'Dashboard' },
  { to: '/vue/management/soal',     icon: 'fa-solid fa-book-open',       label: 'Bank Soal' },
  { to: '/vue/management/hasil',    icon: 'fa-solid fa-pen-to-square',   label: 'Penilaian Essay' },
  { to: '/vue/management/jadwal',   icon: 'fa-solid fa-calendar-days',   label: 'Jadwal Ujian' },
];

// ── Clock ──────────────────────────────────────────────────────────
const updateClock = () => {
  currentTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

// ── Data fetch ─────────────────────────────────────────────────────
const fetchUser = async () => {
  try {
    const { data } = await axios.get('/auth/user');
    userName.value = data.name;
  } catch { userName.value = 'Guru'; }
};

const fetchPaket = async () => {
  loadingPaket.value = true;
  try {
    const { data } = await axios.get('/api/v1/guru/paket-soal');
    paketList.value = data.data || [];
    stats.value.total_paket = paketList.value.length;
    stats.value.total_soal  = paketList.value.reduce((s, p) => s + (p.jumlah_soal || 0), 0);
  } catch { paketList.value = []; } 
  finally { loadingPaket.value = false; loadingStats.value = false; }
};

const fetchJadwal = async () => {
  loadingJadwal.value = true;
  try {
    // Ambil semua jadwal ujian yang terkait paket soal milik guru ini
    const { data } = await axios.get('/kelola/guru/jadwal-terkait');
    jadwalList.value = data.data || [];
    // Hitung yang aktif hari ini
    const today = new Date().toISOString().slice(0, 10);
    stats.value.jadwal_aktif = jadwalList.value.filter(j => j.waktu_mulai?.startsWith(today)).length;
  } catch { jadwalList.value = []; }
  finally { loadingJadwal.value = false; }
};

const fetchPending = async () => {
  loadingGrading.value = true;
  try {
    const { data } = await axios.get('/api/v1/grading/isian');
    pendingList.value = data.data || [];
    stats.value.pending_grading = pendingList.value.length;
  } catch { pendingList.value = []; }
  finally { loadingGrading.value = false; }
};

// ── Helpers ────────────────────────────────────────────────────────
const statusClass = (j) => {
  const now = new Date();
  const mulai   = new Date(j.waktu_mulai_raw);
  const selesai = new Date(j.waktu_selesai_raw);
  if (now >= mulai && now <= selesai) return 'status-aktif';
  if (now < mulai)  return 'status-akan';
  return 'status-selesai';
};
const statusLabel = (j) => {
  const now = new Date();
  const mulai   = new Date(j.waktu_mulai_raw);
  const selesai = new Date(j.waktu_selesai_raw);
  if (now >= mulai && now <= selesai) return 'Berlangsung';
  if (now < mulai)  return 'Akan Datang';
  return 'Selesai';
};

const doLogout = async () => {
  const r = await Swal.fire({
    title: 'Keluar?', text: 'Sesi Anda akan ditutup.',
    icon: 'question', showCancelButton: true,
    confirmButtonColor: '#dc2626', confirmButtonText: 'Logout', cancelButtonText: 'Batal'
  });
  if (r.isConfirmed) window.location.href = '/logout';
};

// ── Lifecycle ──────────────────────────────────────────────────────
onMounted(() => {
  updateClock();
  clockTimer = setInterval(updateClock, 1000);
  fetchUser();
  fetchPaket();
  fetchJadwal();
  fetchPending();
});
onUnmounted(() => { if (clockTimer) clearInterval(clockTimer); });
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
#guru-wrapper { display: flex; min-height: 100vh; background: #f1f5f9; font-family: 'Inter', sans-serif; }

/* ── Sidebar ────────────────────────────────────────────── */
.guru-sidebar {
  width: 240px; min-height: 100vh;
  background: linear-gradient(160deg, #1e3a8a 0%, #1e40af 100%);
  transition: width 0.3s ease; overflow: hidden; flex-shrink: 0;
  display: flex; flex-direction: column;
}
.guru-sidebar.collapsed { width: 68px; }
.guru-sidebar.collapsed .brand-text,
.guru-sidebar.collapsed span { display: none; }
.sidebar-brand {
  display: flex; align-items: center; gap: 12px;
  padding: 1.4rem 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.1);
  color: white; font-size: 1.1rem; font-weight: 700;
}
.sidebar-brand i { font-size: 1.4rem; flex-shrink: 0; }
.sidebar-menu { list-style: none; padding: 0.75rem 0; margin: 0; flex: 1; }
.sidebar-menu li {
  display: flex; align-items: center; gap: 12px;
  padding: 0.75rem 1.2rem; cursor: pointer; color: rgba(255,255,255,0.75);
  font-size: 0.88rem; font-weight: 500; transition: all 0.2s;
  border-left: 3px solid transparent;
}
.sidebar-menu li:hover { background: rgba(255,255,255,0.1); color: white; }
.sidebar-menu li.active { background: rgba(255,255,255,0.15); color: white; border-left-color: #93c5fd; }
.sidebar-menu li i { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
.menu-divider { border-top: 1px solid rgba(255,255,255,0.1); padding: 0 !important; margin: 0.5rem 0; cursor: default !important; }
.logout-item { color: #fca5a5 !important; }
.logout-item:hover { background: rgba(239,68,68,0.15) !important; color: #f87171 !important; }

/* ── Main ───────────────────────────────────────────────── */
.guru-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

/* ── Topbar ─────────────────────────────────────────────── */
.guru-topbar {
  background: white; height: 64px; padding: 0 1.5rem;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: 0 1px 8px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100;
}
.btn-toggle-sidebar { background: none; border: none; font-size: 1.1rem; color: #64748b; cursor: pointer; padding: 0.4rem; border-radius: 8px; }
.btn-toggle-sidebar:hover { background: #f1f5f9; }
.topbar-greeting { flex: 1; font-size: 0.9rem; color: #64748b; }
.topbar-right { display: flex; align-items: center; gap: 1rem; }
.badge-role { background: #eff6ff; color: #1e40af; font-size: 0.78rem; font-weight: 600; padding: 0.35rem 0.8rem; border-radius: 999px; border: 1px solid #bfdbfe; }
.topbar-clock { font-size: 0.85rem; color: #94a3b8; font-variant-numeric: tabular-nums; }

/* ── Content ────────────────────────────────────────────── */
.guru-content { padding: 1.5rem; flex: 1; }

/* ── Stats ──────────────────────────────────────────────── */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
.stat-card {
  background: white; border-radius: 14px; padding: 1.25rem 1.5rem;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card.skeleton { min-height: 90px; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200%; animation: shimmer 1.5s infinite; }
@keyframes shimmer { 0%{background-position:200%} 100%{background-position:-200%} }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.stat-card.blue  .stat-icon { background: #eff6ff; color: #1e40af; }
.stat-card.green .stat-icon { background: #f0fdf4; color: #16a34a; }
.stat-card.amber .stat-icon { background: #fffbeb; color: #d97706; }
.stat-card.red   .stat-icon { background: #fef2f2; color: #dc2626; }
.stat-value { font-size: 1.7rem; font-weight: 700; color: #1e293b; line-height: 1; }
.stat-label { font-size: 0.78rem; color: #94a3b8; margin-top: 3px; font-weight: 500; }

/* ── Guru Grid ──────────────────────────────────────────── */
.guru-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 900px) { .guru-grid { grid-template-columns: 1fr; } }
.full-width { grid-column: 1 / -1; }

/* ── Card ───────────────────────────────────────────────── */
.guru-card { background: white; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
.card-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
.card-head h6 { margin: 0; font-size: 0.9rem; font-weight: 700; color: #334155; }
.btn-link-sm { font-size: 0.78rem; color: #3b82f6; text-decoration: none; font-weight: 600; }
.btn-link-sm:hover { color: #1d4ed8; }
.text-amber { color: #d97706; }
.text-red   { color: #dc2626; }

/* ── Loading / Empty ────────────────────────────────────── */
.loading-state { padding: 2.5rem; text-align: center; }
.empty-state   { padding: 2.5rem; text-align: center; }

/* ── Paket List ─────────────────────────────────────────── */
.paket-list { list-style: none; padding: 0; margin: 0; max-height: 260px; overflow-y: auto; }
.paket-item { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.25rem; border-bottom: 1px solid #f8fafc; transition: background 0.15s; }
.paket-item:hover { background: #f8fafc; }
.paket-item:last-child { border-bottom: none; }
.paket-name  { font-size: 0.88rem; font-weight: 600; color: #334155; display: block; }
.paket-meta  { font-size: 0.75rem; color: #94a3b8; display: block; }
.paket-badges { display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0; }
.badge-count { background: #f1f5f9; color: #475569; font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 999px; font-weight: 600; }
.badge-status { font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 999px; font-weight: 600; }
.badge-status.ready { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.badge-status.draft { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

/* ── Jadwal List ────────────────────────────────────────── */
.jadwal-list { list-style: none; padding: 0; margin: 0; max-height: 260px; overflow-y: auto; }
.jadwal-item { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.25rem; border-bottom: 1px solid #f8fafc; }
.jadwal-item:last-child { border-bottom: none; }
.jadwal-name { font-size: 0.88rem; font-weight: 600; color: #334155; display: block; }
.jadwal-meta { font-size: 0.75rem; color: #94a3b8; display: block; }
.jadwal-status { font-size: 0.72rem; padding: 0.25rem 0.6rem; border-radius: 999px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
.status-aktif   { background: #fef9c3; color: #ca8a04; border: 1px solid #fde68a; }
.status-akan    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.status-selesai { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

/* ── Grading ────────────────────────────────────────────── */
.grading-alert { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.75rem 1.25rem; font-size: 0.85rem; }
.table-guru { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.table-guru thead th { background: #f8fafc; color: #475569; font-weight: 600; padding: 0.7rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; white-space: nowrap; }
.table-guru tbody td { padding: 0.7rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.text-truncate-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.essay-answer { color: #64748b; font-style: italic; }
.badge-bobot { background: #eff6ff; color: #1e40af; font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 999px; font-weight: 700; }
.btn-grade { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 0.75rem; padding: 0.3rem 0.7rem; border-radius: 6px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
.btn-grade:hover { background: #dc2626; color: white; }
</style>
