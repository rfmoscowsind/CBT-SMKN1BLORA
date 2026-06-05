<template>
  <div class="dashboard-wrapper container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold text-dark mb-1">Dashboard Pengawas</h2>
        <p class="text-muted small mb-0">Memantau jalannya ujian aktif secara real-time.</p>
      </div>
      <button class="btn btn-outline-danger px-4" @click="logout">
        <i class="fa-solid fa-power-off me-1"></i> Logout
      </button>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h5 class="card-title fw-bold mb-0">Monitoring Sesi Ujian Aktif</h5>
          <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-users me-1"></i> {{ activeSessions.length }} Sesi Dipantau</span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light text-secondary">
              <tr>
                <th width="5%">No</th>
                <th width="25%">Siswa</th>
                <th width="20%">Mata Pelajaran / Kelas</th>
                <th width="15%">Progress Soal</th>
                <th width="15%">Sisa Waktu</th>
                <th width="10%">Koneksi</th>
                <th width="10%" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="activeSessions.length === 0">
                <td colspan="7" class="text-center text-muted py-4">Tidak ada sesi ujian aktif saat ini.</td>
              </tr>
              <tr v-for="(session, index) in activeSessions" :key="session.id">
                <td>{{ index + 1 }}</td>
                <td>
                  <div class="fw-bold text-dark">{{ session.name }}</div>
                  <div class="small text-muted">@{{ session.username }}</div>
                </td>
                <td>
                  <div class="fw-semibold text-primary">{{ session.mapel }}</div>
                  <div class="small text-muted">{{ session.kelas }}</div>
                </td>
                <td>
                  <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                    {{ session.terjawab }} / {{ session.total_soal }} Terjawab
                  </span>
                </td>
                <td class="fw-bold text-dark">{{ session.sisa_waktu }}</td>
                <td>
                  <span v-if="session.online" class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                    <i class="fa-solid fa-circle text-success" style="font-size: 0.5rem; vertical-align: middle; margin-right: 3px;"></i> Online
                  </span>
                  <span v-else class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Terputus
                  </span>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-danger px-3" @click="resetSesi(session)">
                    Reset Sesi
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const activeSessions = ref([]);
let intervalId = null;

const fetchSessions = async () => {
  try {
    const response = await axios.get('/monitoring/sessions', { headers: { 'Accept': 'application/json' } });
    if (response.data && Array.isArray(response.data)) {
      // Tampilkan sesi aktif dan terkunci agar pengawas bisa reset perangkat.
      activeSessions.value = response.data.filter(s => ['aktif', 'terkunci'].includes(s.status));
    }
  } catch (e) {
    console.error('Gagal mengambil data sesi aktif:', e);
  }
};

const resetSesi = (siswa) => {
  Swal.fire({
    title: 'Reset Sesi Ujian?',
    html: `Anda akan mereset status login ujian atas nama <br><b>${siswa.name} (${siswa.kelas})</b>. <br><br>Jawaban sebelumnya tetap aman.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Reset Sekarang',
    cancelButtonText: 'Batal'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        const response = await axios.post(`/kelola/sesi/${siswa.id}/reset`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data && response.data.success) {
          Swal.fire(
            'Berhasil!',
            'Sesi siswa berhasil di-reset.',
            'success'
          );
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
    if (result.isConfirmed) {
      window.location.href = '/logout';
    }
  });
};

onMounted(() => {
  fetchSessions();
  intervalId = setInterval(fetchSessions, 3000); // Poll every 3 seconds
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});
</script>

<style scoped>
.dashboard-wrapper { background-color: #f8fafc; min-height: 100vh; }
.table th { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
.card { border-radius: 12px; }
</style>
