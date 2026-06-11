<template>
  <div class="student-page">
    <header class="student-topbar">
      <div class="brand">
        <img :src="logoUrl" alt="Logo SMKN 1 Blora">
        <div>
          <strong>CBT SMKN 1 Blora</strong>
          <span>Dashboard Siswa</span>
        </div>
      </div>
      <div class="top-actions">
        <span class="connection-pill" :class="isOnline ? 'online' : 'offline'">
          <i :class="isOnline ? 'fa-solid fa-wifi' : 'fa-solid fa-wifi-slash'"></i>
          {{ isOnline ? 'Terhubung' : 'Terputus' }}
        </span>
        <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Keluar
        </button>
      </div>
    </header>

    <main class="student-shell">
      <section class="student-hero">
        <div class="profile-block">
          <div class="avatar"><i class="fa-solid fa-user-graduate"></i></div>
          <div>
            <span class="eyebrow">Peserta Ujian</span>
            <h2>{{ siswa.nama }}</h2>
            <p>NISN {{ siswa.nisn }} - {{ siswa.kelas }}</p>
          </div>
        </div>
        <div class="profile-meta">
          <div>
            <span>Jurusan</span>
            <strong>{{ siswa.jurusan }}</strong>
          </div>
          <div>
            <span>Jadwal Hari Ini</span>
            <strong>{{ jadwalUjian.length }}</strong>
          </div>
          <div>
            <span>Siap Mulai</span>
            <strong>{{ activeExamCount }}</strong>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header">
          <div>
            <h5>Jadwal Ujian Hari Ini</h5>
            <small>Masukkan token jika diminta lalu mulai ujian sesuai jadwal.</small>
          </div>
          <button class="btn btn-sm btn-light border" @click="fetchDashboard">
            <i class="fa-solid fa-rotate me-1"></i> Refresh
          </button>
        </div>

        <div v-if="isLoading" class="loading-state">
          <span class="spinner-border spinner-border-sm text-primary"></span>
          <span>Memeriksa jadwal ujian...</span>
        </div>

        <div v-else-if="jadwalUjian.length === 0" class="empty-state">
          <i class="fa-solid fa-calendar-check"></i>
          <span>Belum ada jadwal ujian untuk kelas Anda hari ini.</span>
        </div>

        <div v-else class="exam-grid">
          <article v-for="ujian in jadwalUjian" :key="ujian.hash" class="exam-card" :class="ujian.status">
            <div class="exam-head">
              <div class="exam-icon"><i class="fa-solid fa-laptop-code"></i></div>
              <div>
                <h6>{{ ujian.mapel_nama }}</h6>
                <span>{{ ujian.judul_ujian }}</span>
              </div>
              <span class="status-pill" :class="ujian.status">{{ statusLabel(ujian.status) }}</span>
            </div>

            <div class="exam-meta">
              <div><i class="fa-regular fa-clock"></i><span>{{ ujian.waktu_mulai }} - {{ ujian.waktu_selesai }} WIB</span></div>
              <div><i class="fa-solid fa-stopwatch"></i><span>{{ ujian.durasi_menit }} menit</span></div>
              <div><i class="fa-solid fa-list-ol"></i><span>{{ ujian.jumlah_soal }} soal<span v-if="ujian.tipe_soal"> ({{ ujian.tipe_soal }})</span></span></div>
            </div>

            <div class="exam-action">
              <template v-if="ujian.status === 'aktif'">
                <input
                  v-if="ujian.gunakan_token"
                  v-model="ujian.tokenInput"
                  type="text"
                  class="form-control token-input"
                  placeholder="TOKEN"
                  maxlength="12"
                  @keyup.enter="mulaiUjian(ujian)"
                >
                <button class="btn btn-primary w-100" :disabled="ujian.loading || (ujian.gunakan_token && !ujian.tokenInput.trim())" @click="mulaiUjian(ujian)">
                  <span v-if="ujian.loading" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="fa-solid fa-play me-1"></i>
                  {{ ujian.loading ? 'Memeriksa...' : 'Mulai Ujian' }}
                </button>
              </template>
              <div v-else class="passive-action">{{ passiveText(ujian.status) }}</div>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { generateDeviceFingerprint } from '../../utils/fingerprint';
import Swal from 'sweetalert2';

const router = useRouter();
const logoUrl = 'https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png';
const siswa = ref({ nama: '-', nisn: '-', kelas: '-', jurusan: '-' });
const jadwalUjian = ref([]);
const isLoading = ref(true);
const isOnline = ref(navigator.onLine);

const activeExamCount = computed(() => jadwalUjian.value.filter(item => item.status === 'aktif').length);

const updateOnlineStatus = () => {
  isOnline.value = navigator.onLine;
};

const statusLabel = status => ({
  belum_mulai: 'Belum mulai',
  aktif: 'Aktif',
  selesai: 'Selesai',
}[status] || 'Terlewat');

const passiveText = status => ({
  belum_mulai: 'Ujian belum dibuka.',
  selesai: 'Ujian sudah dikerjakan.',
}[status] || 'Ujian tidak dapat dimulai.');

const forceLoginAgain = (message = 'Akun anda terkunci. Mohon hubungi admin.') => {
  Swal.fire({
    title: 'Akun Terkunci',
    text: message,
    icon: 'error',
    confirmButtonText: 'Login Ulang',
    allowOutsideClick: false
  }).then(() => {
    window.location.href = '/login';
  });
};

const fetchDashboard = async () => {
  isLoading.value = true;
  const fpData = generateDeviceFingerprint();
  try {
    const response = await axios.get('/dashboard', {
      params: { device_raw: fpData.components },
      headers: {
        Accept: 'application/json',
        'X-Device-Fingerprint': fpData.hash
      }
    });
    siswa.value = response.data.user;
    jadwalUjian.value = response.data.schedules.map(item => ({ ...item, tokenInput: '', loading: false }));
  } catch (error) {
    if (error.response?.status === 423) {
      forceLoginAgain(error.response.data.message || 'Akun anda terkunci. Mohon hubungi admin.');
      return;
    }
    Swal.fire('Gagal', 'Jadwal ujian tidak dapat dimuat. Silakan muat ulang halaman.', 'error');
  } finally {
    isLoading.value = false;
  }
};

const mulaiUjian = async ujian => {
  const token = (ujian.tokenInput || '').trim().toUpperCase();
  if (ujian.gunakan_token && !token) return Swal.fire('Token Kosong', 'Silakan masukkan token ujian.', 'warning');
  ujian.loading = true;
  const fpData = generateDeviceFingerprint();
  try {
    const response = await axios.post(`/ujian/${ujian.hash}/mulai`, {
      token,
      device_fp: fpData.hash,
      device_raw: fpData.components
    }, {
      headers: {
        Accept: 'application/json',
        'X-Device-Fingerprint': fpData.hash
      }
    });
    router.push({ path: '/vue/ujian', query: { session: response.data.session_hash } });
  } catch (error) {
    if (error.response?.status === 423) {
      forceLoginAgain(error.response.data.message || 'Akun anda terkunci. Mohon hubungi admin.');
      return;
    }
    Swal.fire('Gagal Memulai', error.response?.data?.message || 'Token tidak valid atau ujian belum dapat dimulai.', 'error');
  } finally {
    ujian.loading = false;
  }
};

const logout = () => {
  Swal.fire({
    title: 'Keluar Aplikasi',
    text: 'Apakah Anda yakin ingin keluar dari aplikasi?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#1e3a8a',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Keluar',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) window.location.href = '/logout';
  });
};

onMounted(() => {
  fetchDashboard();
  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);
});

onUnmounted(() => {
  window.removeEventListener('online', updateOnlineStatus);
  window.removeEventListener('offline', updateOnlineStatus);
});
</script>

<style scoped>
.student-page { min-height: 100vh; background: #eef2f7; color: #0f172a; }
.student-topbar { min-height: 72px; padding: 0.85rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(255,255,255,0.94); border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1020; backdrop-filter: blur(12px); }
.brand { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.brand img { width: 42px; height: 42px; object-fit: contain; flex: 0 0 auto; }
.brand strong, .brand span { display: block; }
.brand strong { color: #0f172a; }
.brand span { color: #64748b; font-size: 0.78rem; }
.top-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; justify-content: flex-end; }
.connection-pill { min-height: 34px; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.75rem; border-radius: 8px; border: 1px solid; font-size: 0.82rem; font-weight: 800; }
.connection-pill.online { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.connection-pill.offline { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.student-shell { padding: 1.5rem; max-width: 1180px; margin: 0 auto; }
.student-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, 0.85fr); gap: 1rem; align-items: stretch; margin-bottom: 1rem; }
.profile-block, .profile-meta, .panel { border: 1px solid #dbe4ef; border-radius: 8px; background: #fff; box-shadow: 0 12px 30px rgba(15,23,42,0.04); }
.profile-block { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; }
.avatar { width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; border-radius: 8px; color: #1d4ed8; background: #dbeafe; font-size: 1.7rem; flex: 0 0 auto; }
.eyebrow { color: #0f766e; font-size: 0.74rem; font-weight: 800; text-transform: uppercase; }
.profile-block h2 { margin: 0.1rem 0; color: #0f172a; font-size: 1.55rem; font-weight: 800; letter-spacing: 0; }
.profile-block p { margin: 0; color: #64748b; }
.profile-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; padding: 1rem; }
.profile-meta div { padding: 0.8rem; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; min-width: 0; }
.profile-meta span { display: block; color: #64748b; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; }
.profile-meta strong { display: block; color: #0f172a; font-size: 1.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.panel { padding: 1rem; }
.panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.panel-header h5 { margin: 0; font-weight: 800; }
.panel-header small { color: #64748b; }
.loading-state, .empty-state { min-height: 150px; display: flex; align-items: center; justify-content: center; gap: 0.7rem; padding: 1rem; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
.exam-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.exam-card { display: grid; gap: 1rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fbfdff; }
.exam-card.aktif { border-color: #99f6e4; background: #f0fdfa; }
.exam-head { display: grid; grid-template-columns: 46px minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; }
.exam-icon { width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #dbeafe; color: #1d4ed8; font-size: 1.2rem; }
.exam-head h6 { margin: 0; color: #0f172a; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.exam-head span { color: #64748b; font-size: 0.84rem; }
.status-pill { min-height: 28px; display: inline-flex; align-items: center; justify-content: center; padding: 0.25rem 0.55rem; border-radius: 8px; font-size: 0.74rem; font-weight: 800; border: 1px solid; white-space: nowrap; }
.status-pill.aktif { color: #0f766e; background: #ccfbf1; border-color: #99f6e4; }
.status-pill.belum_mulai { color: #b45309; background: #fffbeb; border-color: #fcd34d; }
.status-pill.selesai { color: #15803d; background: #f0fdf4; border-color: #86efac; }
.status-pill:not(.aktif):not(.belum_mulai):not(.selesai) { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.exam-meta { display: grid; gap: 0.5rem; color: #475569; font-size: 0.88rem; }
.exam-meta div { display: flex; align-items: center; gap: 0.55rem; }
.exam-meta i { width: 18px; color: #64748b; }
.exam-action { display: grid; gap: 0.6rem; margin-top: auto; }
.token-input { text-align: center; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; }
.passive-action { min-height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 8px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; font-weight: 700; }
@media (max-width: 900px) { .student-hero, .exam-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .student-topbar, .panel-header { flex-direction: column; align-items: stretch; } .student-shell { padding: 1rem; } .profile-block { align-items: flex-start; } .profile-meta { grid-template-columns: 1fr; } .exam-head { grid-template-columns: 46px minmax(0, 1fr); } .status-pill { grid-column: 1 / -1; justify-content: flex-start; } .top-actions .btn, .top-actions .connection-pill { width: 100%; justify-content: center; } }
</style>
