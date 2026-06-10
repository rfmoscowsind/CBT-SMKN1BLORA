<template>
  <div class="token-wrapper container-fluid p-4 d-flex align-items-center justify-content-center">
    <div class="card token-card border-0 shadow-lg" style="max-width: 500px; width: 100%;">
      <div class="card-header bg-primary text-white text-center py-4 border-0 rounded-top-3">
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-key me-2"></i> Konfirmasi Token</h4>
        <p class="mb-0 text-white-50 small mt-1">Masukkan token untuk memulai ujian</p>
      </div>
      <div class="card-body p-4 p-md-5">
        
        <div class="text-center mb-4">
            <div class="bg-light p-3 rounded-3 border mb-3 d-inline-block">
                <i class="fa-solid fa-file-signature text-secondary fs-1"></i>
            </div>
            <h5 class="fw-bold text-dark">Siap Memulai?</h5>
            <p class="text-muted small">Pastikan koneksi internet stabil sebelum memasukkan token.</p>
        </div>

        <div class="form-group mb-4">
          <label class="form-label fw-semibold text-secondary">Token Ujian</label>
          <input 
            type="text" 
            class="form-control form-control-lg token-input text-center fw-bold" 
            v-model="inputToken" 
            placeholder="XXXXXX"
            maxlength="10"
            @keyup.enter="mulaiUjian"
            style="letter-spacing: 5px; text-transform: uppercase;"
            :disabled="isLoading"
          >
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-lg fw-bold rounded-3" @click="mulaiUjian" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            {{ isLoading ? 'Memverifikasi...' : 'Mulai Ujian Sekarang' }}
          </button>
          <button class="btn btn-light fw-semibold text-secondary" @click="batalUjian" :disabled="isLoading">
            Kembali ke Dashboard
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import axios from 'axios';
import { generateDeviceFingerprint } from '../../utils/fingerprint';

const router = useRouter();
const route = useRoute();
const inputToken = ref('');
const isLoading = ref(false);
const jadwalHash = ref('');

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

onMounted(() => {
    jadwalHash.value = route.query.jadwal || '';
    if (!jadwalHash.value) {
        Swal.fire('Error', 'Jadwal ujian tidak ditemukan.', 'error').then(() => {
            router.push('/vue/dashboard/siswa');
        });
    }
});

const mulaiUjian = async () => {
    const token = inputToken.value.trim().toUpperCase();
    if (!token) {
        Swal.fire({ icon: 'warning', title: 'Token Kosong', text: 'Silakan masukkan token ujian terlebih dahulu.' });
        return;
    }
    
    isLoading.value = true;
    
    const fpData = generateDeviceFingerprint();
    try {
        const response = await axios.post(`/ujian/${jadwalHash.value}/mulai`, {
            token,
            device_fp: fpData.hash,
            device_raw: fpData.components
        }, { 
            headers: { 
                'Accept': 'application/json',
                'X-Device-Fingerprint': fpData.hash
            } 
        });
        
        if (response.data.success) {
            router.push({ path: '/vue/ujian', query: { session: response.data.session_hash } });
        } else {
            Swal.fire('Gagal', 'Token tidak valid atau ujian tidak bisa dimulai.', 'error');
        }
    } catch (e) {
        if (e.response?.status === 423) {
            forceLoginAgain(e.response?.data?.message || 'Akun anda terkunci. Mohon hubungi admin.');
            return;
        }
        const errorMsg = e.response?.data?.message || 'Terjadi kesalahan pada server. Token mungkin salah atau kedaluwarsa.';
        Swal.fire({ icon: 'error', title: 'Gagal Memulai', text: errorMsg });
    } finally {
        isLoading.value = false;
    }
};

const batalUjian = () => {
    router.push('/vue/dashboard/siswa');
};
</script>

<style scoped>
.token-wrapper { background-color: #f1f5f9; min-height: 100vh; }
.token-card { border-radius: 16px; }
.token-input { background-color: #f8fafc; border: 2px solid #e2e8f0; }
.token-input:focus { border-color: #1e3a8a; box-shadow: 0 0 0 0.25rem rgba(30, 58, 138, 0.1); }
</style>
