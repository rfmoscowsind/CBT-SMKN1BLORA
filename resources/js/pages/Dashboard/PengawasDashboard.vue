<template>
  <div class="watch-page">
    <header class="topbar">
      <div>
        <h5 class="mb-1 fw-bold">Dashboard Pengawas</h5>
        <div class="text-muted small">Monitoring sesi aktif, diperbarui otomatis setiap 3 detik.</div>
      </div>
      <div class="top-actions">
        <span class="live-pill"><i class="fa-solid fa-circle"></i> Live</span>
        <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
          <i class="fa-solid fa-power-off me-1"></i> Keluar
        </button>
      </div>
    </header>

    <main class="dashboard-shell">
      <section class="summary-band">
        <div>
          <span class="eyebrow">Pengawasan Ujian</span>
          <h2>{{ activeSessions.length }} sesi dipantau</h2>
          <p>{{ offlineCount }} koneksi terputus, {{ lockedCount }} sesi terkunci.</p>
        </div>
        <button class="btn btn-primary" @click="fetchSessions">
          <i class="fa-solid fa-rotate me-1"></i> Refresh
        </button>
      </section>

      <section class="stat-grid">
        <div class="stat-card blue">
          <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
          <div><span>Sesi Aktif</span><strong>{{ activeSessions.length }}</strong></div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon"><i class="fa-solid fa-wifi"></i></div>
          <div><span>Online</span><strong>{{ onlineCount }}</strong></div>
        </div>
        <div class="stat-card amber">
          <div class="stat-icon"><i class="fa-solid fa-wifi-slash"></i></div>
          <div><span>Terputus</span><strong>{{ offlineCount }}</strong></div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon"><i class="fa-solid fa-lock"></i></div>
          <div><span>Terkunci</span><strong>{{ lockedCount }}</strong></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header">
          <div>
            <h6>Daftar Sesi Ujian</h6>
            <small>Prioritaskan siswa terputus atau terkunci.</small>
          </div>
        </div>

        <div v-if="activeSessions.length === 0" class="empty-state">
          <i class="fa-solid fa-circle-check"></i>
          <span>Tidak ada sesi ujian aktif saat ini.</span>
        </div>

        <div v-else class="session-list">
          <article v-for="session in activeSessions" :key="session.id" class="session-card" :class="{ offline: !session.online }">
            <div class="session-main">
              <div class="avatar">{{ initials(session.name) }}</div>
              <div class="student-info">
                <strong>{{ session.name }}</strong>
                <span>@{{ session.username }} - {{ session.kelas }}</span>
              </div>
            </div>

            <div class="exam-info">
              <strong>{{ session.mapel }}</strong>
              <span>{{ session.terjawab }} / {{ session.total_soal }} terjawab</span>
              <div class="progress">
                <div class="progress-bar" :style="{ width: progressWidth(session) }"></div>
              </div>
            </div>

            <div class="time-info">
              <span>Sisa waktu</span>
              <strong>{{ session.sisa_waktu }}</strong>
            </div>

            <span class="connection-pill" :class="session.online ? 'online' : 'offline'">
              <i :class="session.online ? 'fa-solid fa-circle' : 'fa-solid fa-triangle-exclamation'"></i>
              {{ session.online ? 'Online' : 'Terputus' }}
            </span>

            <button class="btn btn-sm btn-outline-danger" @click="resetSesi(session)">Reset</button>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const activeSessions = ref([]);
let intervalId = null;

const onlineCount = computed(() => activeSessions.value.filter(s => s.online).length);
const offlineCount = computed(() => activeSessions.value.filter(s => !s.online).length);
const lockedCount = computed(() => activeSessions.value.filter(s => s.status === 'terkunci').length);

const fetchSessions = async () => {
  try {
    const response = await axios.get('/monitoring/sessions', { headers: { 'Accept': 'application/json' } });
    if (response.data && Array.isArray(response.data)) {
      activeSessions.value = response.data.filter(s => ['aktif', 'terkunci'].includes(s.status));
    }
  } catch (e) {
    console.error('Gagal mengambil data sesi aktif:', e);
  }
};

const progressWidth = session => {
  const total = Number(session.total_soal || 0);
  const answered = Number(session.terjawab || 0);
  if (!total) return '0%';
  return `${Math.min(Math.round((answered / total) * 100), 100)}%`;
};

const initials = name => (name || '?')
  .split(' ')
  .filter(Boolean)
  .slice(0, 2)
  .map(part => part[0])
  .join('')
  .toUpperCase();

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
          Swal.fire('Berhasil', 'Sesi siswa berhasil di-reset.', 'success');
          fetchSessions();
        }
      } catch (e) {
        Swal.fire('Error', 'Gagal mereset sesi siswa.', 'error');
      }
    }
  });
};

const logout = () => {
  Swal.fire({
    title: 'Keluar Sistem?',
    text: 'Sesi Pengawas Anda akan ditutup.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Logout',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) window.location.href = '/logout';
  });
};

onMounted(() => {
  fetchSessions();
  intervalId = setInterval(fetchSessions, 3000);
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});
</script>

<style scoped>
.watch-page { min-height: 100vh; background: #eef2f7; color: #0f172a; }
.topbar { min-height: 72px; padding: 0.9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(255,255,255,0.94); border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1020; backdrop-filter: blur(12px); }
.top-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; justify-content: flex-end; }
.live-pill { min-height: 34px; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.75rem; border-radius: 8px; color: #15803d; background: #f0fdf4; border: 1px solid #86efac; font-size: 0.82rem; font-weight: 800; }
.live-pill i { font-size: 0.5rem; }
.dashboard-shell { padding: 1.5rem; }
.summary-band { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem; border: 1px solid #dbe4ef; border-radius: 8px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,0.04); }
.eyebrow { color: #0f766e; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.summary-band h2 { margin: 0.15rem 0; font-size: 1.55rem; font-weight: 800; letter-spacing: 0; }
.summary-band p { margin: 0; color: #64748b; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin: 1rem 0; }
.stat-card { min-height: 104px; display: flex; align-items: center; gap: 0.85rem; padding: 1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 24px rgba(15,23,42,0.035); }
.stat-icon { width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 1.15rem; }
.stat-card span { display: block; color: #64748b; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.stat-card strong { display: block; color: #0f172a; font-size: 1.65rem; line-height: 1.1; }
.stat-card.blue .stat-icon { color: #2563eb; background: #dbeafe; }
.stat-card.green .stat-icon { color: #15803d; background: #dcfce7; }
.stat-card.amber .stat-icon { color: #b45309; background: #fef3c7; }
.stat-card.red .stat-icon { color: #b91c1c; background: #fee2e2; }
.panel { padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; box-shadow: 0 10px 24px rgba(15,23,42,0.035); }
.panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.panel-header h6 { margin: 0; font-weight: 800; }
.panel-header small { color: #64748b; }
.empty-state { min-height: 140px; display: flex; align-items: center; justify-content: center; gap: 0.7rem; padding: 1rem; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
.session-list { display: grid; gap: 0.75rem; }
.session-card { display: grid; grid-template-columns: minmax(220px, 1.1fr) minmax(220px, 1fr) 130px 110px auto; gap: 0.9rem; align-items: center; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fbfdff; }
.session-card.offline { border-color: #fecaca; background: #fffafa; }
.session-main { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.avatar { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 8px; color: #075985; background: #e0f2fe; font-weight: 800; }
.student-info, .exam-info { min-width: 0; display: grid; gap: 0.1rem; }
.student-info strong, .exam-info strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.student-info span, .exam-info span, .time-info span { color: #64748b; font-size: 0.82rem; }
.progress { height: 7px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.progress-bar { height: 100%; background: #0f766e; }
.time-info strong { display: block; font-size: 1rem; }
.connection-pill { min-height: 30px; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.25rem 0.55rem; border-radius: 8px; font-size: 0.76rem; font-weight: 800; border: 1px solid; }
.connection-pill i { font-size: 0.5rem; }
.connection-pill.online { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.connection-pill.offline { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .session-card { grid-template-columns: 1fr 1fr; } .session-card .btn { width: 100%; } }
@media (max-width: 680px) { .topbar, .summary-band { flex-direction: column; align-items: stretch; } .dashboard-shell { padding: 1rem; } .stat-grid, .session-card { grid-template-columns: 1fr; } .connection-pill { justify-content: flex-start; } }
</style>
