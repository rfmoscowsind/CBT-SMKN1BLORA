<template>
  <div class="student-page">
    <nav class="navbar navbar-dark navbar-custom sticky-top">
      <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <img :src="logoUrl" alt="Logo SMKN 1 Blora">
          <span>CBT SMKN 1 Blora</span>
        </a>
        <button class="btn btn-outline-light btn-sm rounded-pill px-3" @click="logout">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Keluar
        </button>
      </div>
    </nav>

    <div class="container py-4">
      <div class="row mb-4">
        <div class="col-lg-4 mb-4 mb-lg-0">
          <div class="card card-custom h-100">
            <div class="profile-header"></div>
            <div class="profile-avatar"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="card-body pt-3">
              <h5 class="fw-bold mb-0 text-dark">{{ siswa.nama }}</h5>
              <p class="text-secondary small mb-3">NISN: {{ siswa.nisn }}</p>
              <hr class="text-muted">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Kelas</span><span class="fw-semibold small">{{ siswa.kelas }}</span>
              </div>
              <div class="d-flex justify-content-between mb-2 gap-3">
                <span class="text-muted small">Jurusan</span><span class="fw-semibold small text-end">{{ siswa.jurusan }}</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted small">Status Server</span>
                <span v-if="isOnline" class="badge bg-success rounded-pill"><i class="fa-solid fa-wifi me-1"></i> Terhubung</span>
                <span v-else class="badge bg-danger rounded-pill"><i class="fa-solid fa-wifi-slash me-1"></i> Terputus</span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <h5 class="fw-bold text-dark mb-3">
            <i class="fa-regular fa-calendar-check text-primary me-2"></i> Jadwal Ujian Hari Ini
          </h5>

          <div v-if="isLoading" class="alert alert-light border-0 shadow-sm rounded-4">
            <span class="spinner-border spinner-border-sm text-primary me-2"></span> Memeriksa jadwal ujian...
          </div>
          <div v-else-if="jadwalUjian.length === 0" class="alert alert-info border-0 shadow-sm rounded-4">
            <i class="fa-solid fa-circle-info me-2"></i> Belum ada jadwal ujian untuk kelas Anda hari ini.
          </div>

          <div class="row">
            <div v-for="ujian in jadwalUjian" :key="ujian.hash" class="col-md-6 mb-4">
              <div class="card card-custom exam-card h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                  <div class="exam-icon-box me-3"><i class="fa-solid fa-laptop-code"></i></div>
                  <div>
                    <h6 class="exam-title">{{ ujian.mapel_nama }}</h6>
                    <span class="exam-subtitle">{{ ujian.judul_ujian }}</span>
                  </div>
                </div>
                <div class="mb-3 px-2">
                  <div class="exam-detail-item"><i class="fa-regular fa-clock"></i> <strong>Waktu:</strong> {{ ujian.waktu_mulai }} - {{ ujian.waktu_selesai }} WIB</div>
                  <div class="exam-detail-item"><i class="fa-solid fa-stopwatch"></i> <strong>Durasi:</strong> {{ ujian.durasi_menit }} Menit</div>
                  <div class="exam-detail-item"><i class="fa-solid fa-list-ol"></i> <strong>Soal:</strong> {{ ujian.jumlah_soal }} Butir <span v-if="ujian.tipe_soal">({{ ujian.tipe_soal }})</span></div>
                </div>
                <div class="mt-auto border-top pt-3">
                  <div v-if="ujian.status === 'belum_mulai'" class="text-center py-2">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fa-solid fa-lock me-1"></i> Belum Waktunya</span>
                  </div>
                  <div v-else-if="ujian.status === 'aktif'">
                    <input v-if="ujian.gunakan_token" v-model="ujian.tokenInput" type="text" class="form-control form-control-token mb-2" placeholder="MASUKKAN TOKEN" maxlength="12" @keyup.enter="mulaiUjian(ujian)">
                    <button class="btn btn-primary-custom w-100 text-white" :disabled="ujian.loading || (ujian.gunakan_token && !ujian.tokenInput.trim())" @click="mulaiUjian(ujian)">
                      <span v-if="ujian.loading" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="fa-solid fa-play me-1"></i> {{ ujian.loading ? 'MEMERIKSA...' : 'MULAI UJIAN' }}
                    </button>
                  </div>
                  <div v-else-if="ujian.status === 'selesai'" class="text-center py-2">
                    <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fa-solid fa-check-double me-1"></i> Sudah Dikerjakan</span>
                  </div>
                  <div v-else class="text-center py-2">
                    <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fa-solid fa-circle-xmark me-1"></i> Terlewat / Tidak Dikerjakan</span>
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
import { onMounted, onUnmounted, ref } from 'vue';
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

const updateOnlineStatus = () => {
  isOnline.value = navigator.onLine;
};

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
  const fpData = generateDeviceFingerprint();
  try {
    const response = await axios.get('/dashboard', { 
      params: {
        device_raw: fpData.components
      },
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
    if (result.isConfirmed) {
      window.location.href = '/logout';
    }
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
.student-page{font-family:Poppins,Arial,sans-serif;background:#f4f7f6;min-height:100vh}.navbar-custom{background:#1e3a8a;padding:1rem 0;box-shadow:0 4px 12px rgba(0,0,0,.1)}.navbar-brand img{width:40px;margin-right:10px}.navbar-brand span{font-weight:600;font-size:1.1rem;letter-spacing:.5px}.card-custom{border:0;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.04);background:#fff;transition:transform .2s ease,box-shadow .2s ease}.profile-header{background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);border-radius:16px 16px 0 0;height:80px}.profile-avatar{width:80px;height:80px;background:#fff;border:4px solid #f4f7f6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#1e3a8a;margin-top:-40px;margin-left:20px;box-shadow:0 4px 10px rgba(0,0,0,.1)}.exam-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(0,0,0,.08)}.exam-icon-box{width:50px;height:50px;border-radius:12px;background:#eff6ff;color:#1e3a8a;display:flex;align-items:center;justify-content:center;font-size:1.5rem}.exam-title{font-weight:700;color:#1e293b;font-size:1.1rem;margin-bottom:.2rem}.exam-subtitle{font-size:.85rem;color:#64748b}.exam-detail-item{font-size:.85rem;color:#475569;margin-bottom:.5rem}.exam-detail-item i{width:20px;color:#94a3b8}.form-control-token{border-radius:8px;border:1px solid #cbd5e1;text-transform:uppercase;text-align:center;letter-spacing:2px;font-weight:600}.form-control-token:focus{border-color:#1e3a8a;box-shadow:0 0 0 .2rem rgba(30,58,138,.15)}.btn-primary-custom{background:#1e3a8a;border:0;border-radius:8px;font-weight:500;padding:.6rem;transition:all .2s ease}.btn-primary-custom:hover:not(:disabled){background:#1d4ed8}.btn-primary-custom:disabled{background:#94a3b8}
</style>
