# 🎨 FRONTEND ARCHITECTURE & UI DESIGN - CBT SMKN 1 BLORA

---

## 📱 TECHNOLOGY STACK

```
Framework: Vue 3 (Composition API)
Build Tool: Vite
State Management: Pinia
UI Framework: Bootstrap 5 + TailwindCSS (utility-first)
HTTP Client: Axios (with interceptors for JWT)
Offline Storage: IndexedDB + LocalStorage
Real-time: WebSocket (optional for live monitoring)
PWA: Service Worker + manifest.json
Icons: Font Awesome 6 / Heroicons
Charts: Chart.js (for analytics - Phase 2)
```

---

## 🏗️ PROJECT STRUCTURE

```
cbt-frontend/
├── src/
│   ├── components/
│   │   ├── common/
│   │   │   ├── Header.vue
│   │   │   ├── Sidebar.vue
│   │   │   ├── Footer.vue
│   │   │   ├── Modal.vue
│   │   │   └── Loading.vue
│   │   ├── auth/
│   │   │   ├── LoginForm.vue
│   │   │   └── LogoutConfirm.vue
│   │   ├── exam/
│   │   │   ├── ExamQuestion.vue
│   │   │   ├── ExamTimer.vue
│   │   │   ├── OptionCard.vue
│   │   │   ├── NavigationPanel.vue
│   │   │   └── ExamFinish.vue
│   │   ├── jadwal/
│   │   │   ├── JadwalList.vue
│   │   │   ├── JadwalForm.vue
│   │   │   └── TokenInput.vue
│   │   ├── soal/
│   │   │   ├── SoalWizard.vue
│   │   │   ├── SoalForm.vue
│   │   │   ├── ImageUpload.vue
│   │   │   └── OptionForm.vue
│   │   └── monitoring/
│   │       ├── MonitoringTable.vue
│   │       ├── StudentCard.vue
│   │       └── LiveScoreBoard.vue
│   ├── pages/
│   │   ├── Auth/
│   │   │   └── Login.vue
│   │   ├── Dashboard/
│   │   │   ├── AdminDashboard.vue
│   │   │   ├── GuraDashboard.vue
│   │   │   ├── PengawasDashboard.vue
│   │   │   └── SiswaDashboard.vue
│   │   ├── Exam/
│   │   │   ├── ExamEntry.vue
│   │   │   ├── ExamInterface.vue
│   │   │   └── ExamResult.vue
│   │   ├── Management/
│   │   │   ├── JadwalManagement.vue
│   │   │   ├── SoalBank.vue
│   │   │   ├── UserManagement.vue
│   │   │   └── BulkImport.vue
│   │   └── Monitoring/
│   │       ├── ProctoringDashboard.vue
│   │       └── SuperAdminMonitor.vue
│   ├── stores/
│   │   ├── auth.js (Pinia store)
│   │   ├── exam.js
│   │   ├── offline.js
│   │   ├── ui.js
│   │   └── monitoring.js
│   ├── services/
│   │   ├── api.js (Axios instance)
│   │   ├── authService.js
│   │   ├── examService.js
│   │   ├── offlineSync.js
│   │   ├── imageProcessor.js
│   │   └── auditLogger.js
│   ├── composables/
│   │   ├── useAuth.js
│   │   ├── useExam.js
│   │   ├── useOfflineMode.js
│   │   ├── useTimer.js
│   │   └── useImage.js
│   ├── utils/
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   ├── constants.js
│   │   └── helpers.js
│   ├── App.vue
│   ├── main.js
│   └── styles/
│       ├── main.css (Tailwind + custom)
│       ├── bootstrap-overrides.scss
│       └── animations.css
├── public/
│   ├── manifest.json (PWA)
│   ├── sw.js (Service Worker)
│   ├── icons/
│   └── images/
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── package.json
```

---

## 🎨 DESIGN SYSTEM

### **Color Palette**

```css
/* Primary Colors */
--color-primary: #2563eb (Blue)
--color-primary-dark: #1e40af
--color-primary-light: #dbeafe

/* Secondary Colors */
--color-success: #10b981 (Green)
--color-warning: #f59e0b (Amber)
--color-danger: #ef4444 (Red)
--color-info: #06b6d4 (Cyan)

/* Neutral Colors */
--color-white: #ffffff
--color-gray-50: #f9fafb
--color-gray-100: #f3f4f6
--color-gray-200: #e5e7eb
--color-gray-600: #4b5563
--color-gray-900: #111827

/* Semantic Colors */
--color-correct: #10b981
--color-incorrect: #ef4444
--color-pending: #f59e0b
--color-offline: #8b5cf6
```

### **Typography**

```css
/* Font Stack */
--font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif
--font-mono: 'Roboto Mono', monospace

/* Heading Scale */
h1: 32px, font-weight: 700, line-height: 1.2
h2: 28px, font-weight: 600, line-height: 1.3
h3: 24px, font-weight: 600, line-height: 1.4
h4: 20px, font-weight: 600, line-height: 1.5

/* Body */
body: 16px, font-weight: 400, line-height: 1.5
small: 14px, font-weight: 400
```

### **Spacing Scale (rem)**

```
xs: 0.25rem (4px)
sm: 0.5rem (8px)
md: 1rem (16px)
lg: 1.5rem (24px)
xl: 2rem (32px)
2xl: 3rem (48px)
```

### **Border Radius**

```
sm: 4px
md: 8px
lg: 12px
full: 9999px
```

---

## 📐 RESPONSIVE BREAKPOINTS

```
Mobile: 320px - 639px (portrait)
Tablet: 640px - 1023px
Desktop: 1024px+
Large Desktop: 1280px+

Media Queries:
@media (max-width: 640px) { /* Mobile */ }
@media (min-width: 641px) and (max-width: 1023px) { /* Tablet */ }
@media (min-width: 1024px) { /* Desktop */ }
```

---

## 🔐 AUTHENTICATION FLOW

### **Login Page (Mobile & Desktop)**

```vue
<!-- pages/Auth/Login.vue -->
<template>
  <div class="login-container">
    <!-- Logo section -->
    <div class="logo-section">
      <img src="@/assets/logo.svg" alt="CBT Logo" class="logo">
      <h1>CBT SMKN 1 BLORA</h1>
      <p class="subtitle">Computer-Based Test</p>
    </div>

    <!-- Login form -->
    <form @submit.prevent="handleLogin" class="login-form">
      <div class="form-group">
        <label for="username">Username (NISN/NIP)</label>
        <input
          id="username"
          v-model="credentials.username"
          type="text"
          placeholder="123456789"
          class="form-control"
          autocomplete="username"
          @keyup.enter="handleLogin"
        >
        <span v-if="errors.username" class="text-danger">{{ errors.username }}</span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          id="password"
          v-model="credentials.password"
          type="password"
          placeholder="••••••••"
          class="form-control"
          autocomplete="current-password"
          @keyup.enter="handleLogin"
        >
        <span v-if="errors.password" class="text-danger">{{ errors.password }}</span>
      </div>

      <button 
        type="submit" 
        class="btn btn-primary btn-block"
        :disabled="isLoading"
      >
        <span v-if="isLoading" class="spinner"></span>
        {{ isLoading ? 'Masuk...' : 'Masuk' }}
      </button>

      <div v-if="errors.global" class="alert alert-danger mt-3">
        {{ errors.global }}
      </div>
    </form>

    <!-- Offline warning -->
    <div v-if="!isOnline" class="alert alert-warning mt-3">
      ⚠️ Tidak ada koneksi internet. Silahkan periksa koneksi Anda.
    </div>

    <!-- Footer -->
    <div class="login-footer">
      <p>© 2026 SMKN 1 Blora</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useOnline } from '@vueuse/core'

const router = useRouter()
const authStore = useAuthStore()
const { isOnline } = useOnline()

const credentials = ref({ username: '', password: '' })
const errors = ref({})
const isLoading = ref(false)

const handleLogin = async () => {
  if (!isOnline.value) {
    errors.value.global = 'Tidak ada koneksi internet'
    return
  }

  isLoading.value = true
  errors.value = {}

  try {
    await authStore.login(credentials.value.username, credentials.value.password)
    
    // Redirect berdasarkan role
    const role = authStore.user.roles[0]
    switch (role) {
      case 'SuperAdmin':
      case 'Admin':
        router.push('/admin/dashboard')
        break
      case 'Guru':
        router.push('/guru/dashboard')
        break
      case 'Pengawas':
        router.push('/pengawas/dashboard')
        break
      case 'Siswa':
        router.push('/siswa/dashboard')
        break
    }
  } catch (error) {
    errors.value.global = error.response?.data?.error || 'Login gagal'
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped lang="scss">
.login-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 2rem;
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
}

.logo-section {
  text-align: center;
  margin-bottom: 3rem;
  color: white;

  .logo {
    width: 60px;
    height: 60px;
    margin-bottom: 1rem;
  }

  h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 0.5rem;
  }

  .subtitle {
    font-size: 14px;
    opacity: 0.9;
  }
}

.login-form {
  width: 100%;
  max-width: 400px;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);

  .form-group {
    margin-bottom: 1.5rem;

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #111827;
    }

    input {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.3s;

      &:focus {
        border-color: #2563eb;
        outline: none;
      }
    }
  }

  .btn {
    width: 100%;
    padding: 0.75rem;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;

    &.btn-primary {
      background: #2563eb;
      color: white;

      &:hover:not(:disabled) {
        background: #1e40af;
      }

      &:disabled {
        opacity: 0.7;
        cursor: not-allowed;
      }
    }
  }
}

.login-footer {
  margin-top: 2rem;
  text-align: center;
  color: white;
  font-size: 14px;
}

@media (max-width: 640px) {
  .login-container {
    padding: 1rem;
  }

  .login-form {
    border-radius: 8px;
    padding: 1.5rem;
  }
}
</style>
```

---

## 📚 EXAM INTERFACE (Main Component)

### **ExamInterface.vue**

```vue
<template>
    <div class="exam-wrapper">
        <div class="exam-topbar">
            <div class="container-fluid px-4">
                <div class="row align-items-center">
                    <div class="col-4 d-none d-md-block">
                        <h5 class="mb-0 fw-bold text-dark">{{ identitas.mapel }}</h5>
                        <small class="text-secondary">{{ identitas.judul }}</small>
                    </div>
                    <div class="col-12 col-md-4 text-center">
                        <div class="timer-box" :class="{ 'warning': isTimeWarning }">
                            <i class="fa-regular fa-clock me-1"></i> {{ formattedTime }}
                        </div>
                    </div>
                    <div class="col-4 d-none d-md-flex justify-content-end align-items-center">
                        <div class="text-end me-3">
                            <div class="fw-semibold" style="font-size: 0.9rem;">{{ siswa.nama }}</div>
                            <div class="text-muted" style="font-size: 0.8rem;">{{ siswa.kelas }}</div>
                        </div>
                        <button @click="toggleFullscreen" class="btn btn-light border shadow-sm">
                            <i class="fa-solid fa-expand"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="d-flex justify-content-center align-items-center" style="height: 70vh;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div v-else class="container-fluid px-4 py-4">
            <div class="row">
                
                <div class="col-lg-8 mb-4">
                    <div class="question-card p-4 d-flex flex-column">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <span class="question-number-badge">Soal Nomor {{ currentNomor }}</span>
                            <span class="badge bg-secondary">{{ currentSoal.tipe === 'PG' ? 'Pilihan Ganda' : 'Isian Singkat' }}</span>
                        </div>

                        <div class="mb-4">
                            <div class="question-text mb-3" v-text="currentSoal.pertanyaan"></div>
                            <img 
                                v-if="currentSoal.gambar_url" 
                                :src="currentSoal.gambar_url" 
                                class="img-fluid rounded border mb-3 shadow-sm" 
                                alt="Ilustrasi Soal"
                                style="max-height: 300px;"
                            >
                        </div>

                        <div v-if="currentSoal.tipe === 'PG'" class="options-container flex-grow-1">
                            <div v-for="(opsi, index) in currentSoal.opsi" :key="opsi.hash_id">
                                <input 
                                    type="radio" 
                                    :id="'opsi_' + opsi.hash_id" 
                                    :value="opsi.hash_id"
                                    v-model="currentSoal.jawaban_siswa"
                                    class="option-input"
                                    @change="simpanJawaban(currentSoal)"
                                    :disabled="isSaving"
                                >
                                <label :for="'opsi_' + opsi.hash_id" class="option-label">
                                    <span class="option-letter">{{ abjad[index] }}</span>
                                    <span v-text="opsi.teks"></span>
                                </label>
                            </div>
                        </div>

                        <div v-else-if="currentSoal.tipe === 'ISIAN'" class="flex-grow-1">
                            <textarea 
                                class="form-control" 
                                rows="5" 
                                v-model="currentSoal.jawaban_siswa"
                                placeholder="Ketik jawaban Anda di sini..."
                                @blur="simpanJawaban(currentSoal)"
                                :disabled="isSaving"
                            ></textarea>
                        </div>

                        <div class="d-flex justify-content-between mt-5 pt-3 border-top align-items-center">
                            <button class="btn btn-outline-secondary btn-navigation" @click="prevSoal" :disabled="currentNomor <= 1 || isFetchingSoal">
                                <i class="fa-solid fa-chevron-left me-1"></i> Sebelumnya
                            </button>
                            
                            <div class="form-check form-switch fs-5 mb-0">
                                <input class="form-check-input" type="checkbox" id="raguCheck" v-model="currentSoal.ragu" @change="simpanJawaban(currentSoal)" :disabled="isSaving">
                                <label class="form-check-label text-warning fw-bold ms-2" for="raguCheck" style="cursor: pointer;">
                                    Ragu-Ragu
                                </label>
                            </div>
                            
                            <button v-if="currentNomor < totalSoal" class="btn btn-primary btn-navigation" @click="nextSoal">
                                Selanjutnya <i class="fa-solid fa-chevron-right ms-1"></i>
                            </button>

                            <button v-else class="btn btn-success btn-navigation" @click="bukaModalKonfirmasi">
                                <i class="fa-solid fa-flag-checkered me-1"></i> Selesai Ujian
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="grid-card p-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Navigasi Soal</h6>
                        <div class="grid-container mb-4">
                            <button 
                                v-for="item in navigasi" 
                                :key="item.nomor"
                                class="grid-btn"
                                :class="{ 
                                    'active': currentNomor === item.nomor,
                                    'answered': item.terjawab && !item.ragu,
                                    'ragu': item.ragu
                                }"
                                @click="goToSoal(item.nomor)"
                            >
                                {{ item.nomor }}
                            </button>
                        </div>
                        <div class="p-3 bg-light rounded-3 border" style="font-size: 0.85rem;">
                            <div class="d-flex align-items-center mb-2">
                                <span class="d-inline-block bg-success rounded me-2" style="width: 15px; height: 15px;"></span> Sudah Dijawab
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="d-inline-block bg-warning rounded me-2" style="width: 15px; height: 15px;"></span> Ragu-Ragu
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="d-inline-block bg-white border rounded me-2" style="width: 15px; height: 15px;"></span> Belum Dijawab
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="d-inline-block border border-primary border-3 rounded me-2" style="width: 15px; height: 15px;"></span> Posisi Saat Ini
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal fade" id="modalKonfirmasiSelesai" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" ref="modalKonfirmasiSelesai">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header modal-custom-header">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-check me-2"></i> Konfirmasi Selesai</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="mb-4">Apakah Anda yakin ingin mengakhiri ujian ini? Pastikan semua soal telah terjawab dengan benar.</p>
                        
                        <div class="row text-center mb-2">
                            <div class="col-6">
                                <div class="p-3 bg-success bg-opacity-10 rounded border border-success">
                                    <h3 class="text-success mb-0 fw-bold">{{ jumlahTerjawab }}</h3>
                                    <small class="text-secondary fw-semibold">Sudah Dijawab</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger">
                                    <h3 class="text-danger mb-0 fw-bold">{{ jumlahKosong }}</h3>
                                    <small class="text-secondary fw-semibold">Belum Dijawab</small>
                                </div>
                            </div>
                        </div>

                        <div v-if="jumlahKosong > 0" class="alert alert-warning mt-3 mb-0" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Peringatan: Masih ada <strong>{{ jumlahKosong }} soal</strong> yang kosong!
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 rounded-bottom">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cek Kembali</button>
                        <button type="button" class="btn btn-primary px-4" @click="eksekusiSubmitUjian">
                            Ya, Selesai Ujian
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="sync-indicator">
            <div v-if="!isOnline" class="badge bg-danger p-3 shadow-lg fs-6 rounded-pill">
                <i class="fa-solid fa-wifi-slash me-2"></i> Koneksi Terputus - Jawaban Disimpan Lokal
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import * as bootstrap from 'bootstrap';
import axios from 'axios';

const router = useRouter();
const route = useRoute();
const sessionHash = ref('');

const siswa          = ref({ nama: '', kelas: '' });
// State navigasi + soal (diisi saat fetchExamData + fetchSoal)
const navigasi       = ref([]);
const currentSoal    = ref(null);
const currentNomor   = ref(1);
const isFetchingSoal = ref(false);
const totalSoal      = ref(0);
const identitas      = ref({});
const abjad = ['A', 'B', 'C', 'D', 'E'];

const isOnline = ref(navigator.onLine);
const isSaving = ref(false);
const isSubmitting = ref(false);
const isLoading = ref(true);

let debounceTimer = null;
let modalKonfirmasiInstance = null;

const sisaWaktuDetik = ref(0); 
let intervalTimer = null;
let STORAGE_KEY = '';

// ── Ping (heartbeat) ──────────────────────────────────────────────────────────
let pingInterval = null;

/** Kirim heartbeat ke server agar server tahu siswa masih aktif mengerjakan */
const sendPing = async () => {
    if (!sessionHash.value || !navigator.onLine) return;
    try {
        const res = await axios.post(`/ujian/sesi/${sessionHash.value}/ping`, {}, {
            headers: { 'Accept': 'application/json' }
        });
        // Sync sisa waktu dari server jika ada selisih > 5 detik
        if (res.data?.sisa_detik !== undefined && Math.abs(sisaWaktuDetik.value - res.data.sisa_detik) > 5) {
            sisaWaktuDetik.value = res.data.sisa_detik;
        }
    } catch (e) {
        // Abaikan error ping (jangan ganggu pengalaman ujian)
        if (e.response?.status === 410) {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        }
    }
};


const jumlahKosong   = computed(() => navigasi.value.filter(n => !n.terjawab).length);
const jumlahTerjawab = computed(() => navigasi.value.filter(n => n.terjawab).length);
const formattedTime = computed(() => {
    const jam = Math.floor(sisaWaktuDetik.value / 3600);
    const menit = Math.floor((sisaWaktuDetik.value % 3600) / 60);
    const detik = sisaWaktuDetik.value % 60;
    return `${String(jam).padStart(2, '0')}:${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
});
const isTimeWarning = computed(() => sisaWaktuDetik.value > 0 && sisaWaktuDetik.value < 60); 

const fetchExamData = async () => {
    // ????????? Ambil METADATA saja ??? soal TIDAK dikirim di sini (anti-leak) ?????????????????????
    isLoading.value = true;
    try {
        const res = await axios.get(`/ujian/sesi/${sessionHash.value}`, {
            headers: { 'Accept': 'application/json' }
        });

        if (res.data.redirect || res.data.status === 'selesai') {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
            return;
        }

        identitas.value   = res.data.identitas;
        siswa.value       = res.data.siswa;
        navigasi.value    = res.data.navigasi;   // [{nomor, terjawab, ragu}]
        totalSoal.value   = res.data.total_soal;

        // Sync timer dari server saat init
        sisaWaktuDetik.value = res.data.sisa_detik;
        startTimer();

        // Muat soal pertama
        await fetchSoal(1);
    } catch (e) {
        console.error('Gagal memuat data ujian', e);
        Swal.fire({
            icon: 'error',
            title: 'Gagal Memuat Ujian',
            text: 'Terjadi kesalahan saat memuat data ujian. Silakan coba lagi.',
            confirmButtonColor: '#1e3a8a'
        });
    } finally {
        isLoading.value = false;
    }
};

/**
 * fetchSoal(nomor) ??? ambil 1 soal dari server berdasarkan nomor.
 * Setiap request ke server sekaligus me-refresh sisa waktu dari server.
 * Anti-leak: server TIDAK pernah mengirim is_benar.
 */
const fetchSoal = async (nomor) => {
    isFetchingSoal.value = true;
    try {
        const res = await axios.get(`/ujian/sesi/${sessionHash.value}/soal`, {
            params: { nomor },
            headers: { 'Accept': 'application/json' }
        });

        if (res.data.redirect || res.data.status === 'selesai') {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
            return;
        }

        // ?????? Sync sisa waktu dari server ??????????????????????????????????????????????????????????????????????????????????????????
        // Toleransi: hanya update jika beda > 5 detik (hindari flicker)
        if (Math.abs(sisaWaktuDetik.value - res.data.sisa_detik) > 5) {
            sisaWaktuDetik.value = res.data.sisa_detik;
        }

        // ?????? Set soal aktif ?????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
        currentSoal.value = {
            nomor:         res.data.nomor,
            hash_id:       res.data.hash_id,
            tipe:          res.data.tipe,
            pertanyaan:    res.data.pertanyaan,
            gambar_url:    res.data.gambar_url,
            opsi:          res.data.opsi,
            jawaban_siswa: res.data.jawaban_siswa,
            ragu:          res.data.ragu,
        };
        currentNomor.value = nomor;

        // Update navigasi lokal agar nomor saat ini tanda terjawab akurat
        const nav = navigasi.value.find(n => n.nomor === nomor);
        if (nav) {
            nav.ragu = res.data.ragu;
        }

        // Restore jawaban dari localStorage jika server tidak punya (offline recovery)
        muatJawabanLokal(nomor);
    } catch (e) {
        if (e.response?.status === 410) {
            // Waktu habis ??? server sudah auto-submit
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        } else {
            console.error('Gagal memuat soal', e);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memuat Soal',
                text: 'Terjadi kesalahan saat memuat soal. Silakan coba lagi.',
                confirmButtonColor: '#1e3a8a'
            });
        }
    } finally {
        isFetchingSoal.value = false;
    }
};


/** Muat jawaban dari localStorage untuk soal tertentu (offline recovery) */
const muatJawabanLokal = (nomor) => {
    const key = `${STORAGE_KEY}_${nomor}`;
    const saved = localStorage.getItem(key);
    if (!saved) return;

    try {
        const data = JSON.parse(saved);
        // Hanya restore jika server tidak punya jawaban
        if (!currentSoal.value.jawaban_siswa && data.jawaban_siswa) {
            currentSoal.value.jawaban_siswa = data.jawaban_siswa;
        }
        // Restore status ragu
        if (data.ragu !== undefined) {
            currentSoal.value.ragu = data.ragu;
        }
    } catch (e) {
        console.warn('Gagal parse localStorage untuk soal', nomor, e);
    }
};

const simpanJawabanLokal = () => {
    if (!currentSoal.value || !currentNomor.value) return;
    const key = `${STORAGE_KEY}_${currentNomor.value}`;
    localStorage.setItem(key, JSON.stringify({
        jawaban_siswa: currentSoal.value.jawaban_siswa,
        ragu: currentSoal.value.ragu,
    }));
};

const startTimer = () => {
    if(intervalTimer) clearInterval(intervalTimer);
    intervalTimer = setInterval(() => {
        if (sisaWaktuDetik.value > 0) {
            sisaWaktuDetik.value--;
        } else {
            clearInterval(intervalTimer);
            waktuHabis();
        }
    }, 1000);
};

const waktuHabis = () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    if (modalKonfirmasiInstance) modalKonfirmasiInstance.hide(); 
    if (Swal.isVisible()) Swal.close();
    
    Swal.fire({
        title: 'Waktu Habis!',
        text: 'Sistem sedang menyimpan seluruh jawaban Anda...',
        icon: 'warning',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        prosesSubmitKeServer(); 
    });
};

const bukaModalKonfirmasi = () => {
    if(!modalKonfirmasiInstance) {
        modalKonfirmasiInstance = new bootstrap.Modal(document.getElementById('modalKonfirmasiSelesai'));
    }
    modalKonfirmasiInstance.show();
};

const eksekusiSubmitUjian = () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    modalKonfirmasiInstance.hide();
    if (Swal.isVisible()) Swal.close();
    
    Swal.fire({
        title: 'Memproses...',
        text: 'Sedang menyinkronkan data ke server',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    prosesSubmitKeServer();
};

const prosesSubmitKeServer = async () => {
    try {
        await axios.post(`/ujian/sesi/${sessionHash.value}/selesai`, {}, { headers: { 'Accept': 'application/json' } });
        // Hapus semua localStorage per soal
        for (let i = 1; i <= totalSoal.value; i++) {
            localStorage.removeItem(`${STORAGE_KEY}_${i}`);
        }
        Swal.fire({
            title: 'Ujian Selesai!',
            text: 'Jawaban Anda telah berhasil tersimpan.',
            icon: 'success',
            confirmButtonText: 'Lihat Hasil',
            confirmButtonColor: '#1e3a8a',
            allowOutsideClick: false
        }).then(() => {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        });
    } catch (e) {
        isSubmitting.value = false;
        Swal.fire('Error', 'Gagal memproses ujian. Cek koneksi Anda.', 'error');
    }
};

const simpanJawabanKeServer = async (soal) => {
    try {
        const res = await axios.post(`/ujian/sesi/${sessionHash.value}/simpan`, {
            soal_hash: soal.hash_id,
            opsi_hash: soal.tipe === 'PG' ? soal.jawaban_siswa : null,
            essay: soal.tipe === 'ISIAN' ? soal.jawaban_siswa : null,
            ragu: soal.ragu
        }, { headers: { 'Accept': 'application/json' } });

        // Sync sisa waktu dari respons server
        if (res.data?.sisa_detik !== undefined) {
            if (Math.abs(sisaWaktuDetik.value - res.data.sisa_detik) > 5) {
                sisaWaktuDetik.value = res.data.sisa_detik;
            }
        }

        // Update status terjawab di navigasi lokal
        const nav = navigasi.value.find(n => n.nomor === currentNomor.value);
        if (nav && soal.jawaban_siswa) nav.terjawab = true;

    } catch (e) {
        if (e.response?.status === 410) {
            // Server bilang waktu habis ??? redirect ke hasil
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        } else {
            console.error('Gagal sync ke server', e);
            Swal.fire({
                icon: 'warning',
                title: 'Gagal Menyimpan Jawaban',
                text: 'Jawaban tersimpan secara lokal dan akan dikirim saat koneksi pulih.',
                confirmButtonColor: '#1e3a8a'
            });
        }
    }
};

const nextSoal = async () => {
    if (currentNomor.value < totalSoal.value) {
        await fetchSoal(currentNomor.value + 1);
    }
};
const prevSoal = async () => {
    if (currentNomor.value > 1) {
        await fetchSoal(currentNomor.value - 1);
    }
};
const goToSoal = async (nomor) => {
    await fetchSoal(nomor);
};

const simpanJawaban = (soal) => {
    clearTimeout(debounceTimer);
    isSaving.value = true;
    simpanJawabanLokal();
    
    debounceTimer = setTimeout(() => { 
        simpanJawabanKeServer(soal).then(() => {
            isSaving.value = false; 
        });
    }, 1000); 
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
    else if (document.exitFullscreen) document.exitFullscreen();
};

const updateOnlineStatus = () => { isOnline.value = navigator.onLine; };

onMounted(() => {
    sessionHash.value = route.query.session || '';
    if (!sessionHash.value) {
        router.push('/vue/dashboard/siswa');
        return;
    }
    
    fetchExamData();
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    // ── Ping setiap 20 detik agar server deteksi siswa aktif/offline ──
    pingInterval = setInterval(sendPing, 20000);
});

onUnmounted(() => {
    if (intervalTimer) clearInterval(intervalTimer);
    if (pingInterval) clearInterval(pingInterval);
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});
</script>

<style scoped>
.exam-wrapper {
    background-color: #f1f5f9;
    min-height: 100vh;
}
.exam-topbar {
    background-color: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    padding: 0.8rem 0;
    position: sticky;
    top: 0;
    z-index: 1020;
}
.timer-box {
    background-color: #fee2e2;
    color: #dc2626;
    font-weight: 700;
    font-size: 1.25rem;
    padding: 0.4rem 1.2rem;
    border-radius: 8px;
    border: 1px solid #fca5a5;
    display: inline-block;
}
.timer-box.warning {
    animation: pulse 1s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.question-card {
    background: #fff;
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    min-height: 60vh;
}
.question-number-badge {
    background-color: #1e3a8a;
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}
.question-text {
    font-size: 1.15rem;
    color: #1e293b;
    line-height: 1.6;
}

.option-label {
    display: block;
    padding: 1rem 1.2rem;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1.05rem;
    color: #334155;
}
.option-label:hover {
    border-color: #94a3b8;
    background-color: #f8fafc;
}
.option-input:checked + .option-label {
    border-color: #1e3a8a;
    background-color: #eff6ff;
    font-weight: 500;
    color: #1e3a8a;
}
.option-input { display: none; }
.option-letter {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 28px;
    text-align: center;
    border-radius: 50%;
    background-color: #e2e8f0;
    margin-right: 10px;
    font-weight: 600;
    font-size: 0.9rem;
}
.option-input:checked + .option-label .option-letter {
    background-color: #1e3a8a;
    color: white;
}

.grid-card {
    background: #fff;
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.grid-container {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 5px;
}
.grid-btn {
    width: 100%;
    aspect-ratio: 1;
    border: 1px solid #cbd5e1;
    background-color: #fff;
    border-radius: 8px;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.grid-btn:hover { background-color: #f1f5f9; }
.grid-btn.answered {
    background-color: #10b981; 
    border-color: #059669;
    color: white;
}
.grid-btn.ragu {
    background-color: #f59e0b; 
    border-color: #d97706;
    color: white;
}
.grid-btn.active {
    border: 3px solid #1e3a8a;
    transform: scale(1.05);
}
.btn-navigation {
    border-radius: 8px;
    padding: 0.6rem 1.5rem;
    font-weight: 500;
}
.sync-indicator {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1050;
}

.modal-custom-header {
    background-color: #1e3a8a;
    color: white;
    border-radius: 16px 16px 0 0;
}
.modal-content {
    border-radius: 16px;
    border: none;
}
</style>

```

---

## 📊 DASHBOARD LAYOUTS

### **Admin/SuperAdmin Dashboard**

```
┌─────────────────────────────────────────────────────────────┐
│  Header: Logo | Greeting | Notifications | Profile Menu    │
├─────────────────────────────────────────────────────────────┤
│ Sidebar          │  Main Content Area                      │
├─────────────────┼──────────────────────────────────────────┤
│ • Dashboard     │  📊 Dashboard Admin                      │
│ • Jadwal Ujian  │  ┌──────────────────────────────────────┐│
│ • Monitoring    │  │ 4 Cards:                              ││
│ • Laporan       │  │ ├─ Ujian Aktif: 12                   ││
│ • User Mgmt     │  │ ├─ Peserta Total: 1500               ││
│ • Soal Bank     │  │ ├─ Sedang Mengerjakan: 1200          ││
│ • Settings      │  │ └─ Selesai Ujian: 300                ││
│                 │  └──────────────────────────────────────┘│
│                 │  ┌──────────────────────────────────────┐│
│                 │  │ Tabel: Jadwal Ujian Terbaru          ││
│                 │  │ (dengan pagination & filters)         ││
│                 │  └──────────────────────────────────────┘│
│                 │  ┌──────────────────────────────────────┐│
│                 │  │ Tabel: Monitoring Live Score         ││
│                 │  │ (SuperAdmin only)                    ││
│                 │  └──────────────────────────────────────┘│
└─────────────────┴──────────────────────────────────────────┘
```

---

### **Student Exam Result Page**

```vue
<!-- pages/Exam/ExamResult.vue -->
<template>
  <div class="result-container">
    <!-- Success Message -->
    <div v-if="ujian.tampilkan_nilai_akhir" class="result-success">
      <i class="icon-check-circle"></i>
      <h2>Ujian Selesai!</h2>
      <p>Terima kasih telah mengerjakan ujian</p>
    </div>
    <div v-else class="result-pending">
      <i class="icon-clock"></i>
      <h2>Ujian Telah Disimpan</h2>
      <p>Hasil ujian akan ditampilkan setelah dinilai guru</p>
    </div>

    <!-- Score Display (if visible) -->
    <div v-if="ujian.tampilkan_nilai_akhir" class="score-display">
      <div class="score-card">
        <h3>Nilai Anda</h3>
        <div class="score-value">{{ nilai.total }}</div>
        <p class="score-label">dari 100</p>
      </div>

      <div class="score-breakdown">
        <div class="breakdown-item">
          <span>Soal PG (Otomatis):</span>
          <strong>{{ nilai.score_pg }}</strong>
        </div>
        <div class="breakdown-item">
          <span>Soal Isian (Manual):</span>
          <strong>{{ nilai.score_isian || 'Pending' }}</strong>
        </div>
      </div>
    </div>

    <!-- Answer Summary -->
    <div class="answer-summary">
      <h3>Ringkasan Jawaban</h3>
      <div class="summary-grid">
        <div class="summary-card">
          <div class="summary-number">{{ totalJawaban }}</div>
          <p>Soal Terjawab</p>
        </div>
        <div class="summary-card">
          <div class="summary-number">{{ totalSoal - totalJawaban }}</div>
          <p>Tidak Terjawab</p>
        </div>
        <div class="summary-card">
          <div class="summary-number">{{ durationMinutes }} Menit</div>
          <p>Durasi Ujian</p>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
      <button class="btn btn-primary" @click="goToDashboard">
        <i class="icon-arrow-left"></i> Kembali ke Dashboard
      </button>
    </div>

    <!-- Footer Info -->
    <div class="result-footer">
      <small>Session ID: {{ sesiId }}</small>
      <small>Waktu Selesai: {{ finishTime }}</small>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useExamStore } from '@/stores/exam'

const router = useRouter()
const examStore = useExamStore()

const sesiId = ref(null)
const ujian = ref({})
const nilai = ref({})
const totalJawaban = ref(0)
const totalSoal = ref(0)
const durationMinutes = ref(0)
const finishTime = ref('')

const goToDashboard = () => {
  router.push('/siswa/dashboard')
}

onMounted(async () => {
  sesiId.value = router.currentRoute.value.params.sesiId
  
  // Fetch exam results
  const result = await examStore.getExamResult(sesiId.value)
  Object.assign(ujian.value, result.exam)
  Object.assign(nilai.value, result.score)
  totalJawaban.value = result.answered_count
  totalSoal.value = result.total_questions
  durationMinutes.value = result.duration_minutes
  finishTime.value = result.finished_at
})
</script>

<style scoped lang="scss">
.result-container {
  max-width: 600px;
  margin: 0 auto;
  padding: 2rem;
  min-height: 100vh;
  background: linear-gradient(135deg, #f0fdf4 0%, #f0f9ff 100%);
}

.result-success,
.result-pending {
  text-align: center;
  padding: 2rem;
  background: white;
  border-radius: 12px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);

  i {
    font-size: 48px;
    color: #10b981;
    display: block;
    margin-bottom: 1rem;
  }

  h2 {
    margin: 0 0 0.5rem 0;
    color: #111827;
  }

  p {
    margin: 0;
    color: #6b7280;
  }
}

.result-pending {
  i {
    color: #f59e0b;
  }
}

.score-display {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  margin-bottom: 2rem;
  text-align: center;

  .score-card {
    margin-bottom: 2rem;
    padding: 2rem;
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    border-radius: 12px;
    color: white;

    h3 {
      margin: 0 0 1rem 0;
      font-size: 14px;
      text-transform: uppercase;
      opacity: 0.9;
    }

    .score-value {
      font-size: 48px;
      font-weight: 700;
      margin: 0;
      line-height: 1;
    }

    .score-label {
      margin: 0.5rem 0 0 0;
      opacity: 0.9;
    }
  }

  .score-breakdown {
    display: flex;
    flex-direction: column;
    gap: 1rem;

    .breakdown-item {
      display: flex;
      justify-content: space-between;
      padding: 1rem;
      background: #f9fafb;
      border-radius: 8px;

      span {
        color: #6b7280;
      }

      strong {
        color: #111827;
      }
    }
  }
}

.answer-summary {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  margin-bottom: 2rem;

  h3 {
    margin: 0 0 1.5rem 0;
    color: #111827;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;

    .summary-card {
      padding: 1.5rem;
      background: #f9fafb;
      border-radius: 8px;
      text-align: center;

      .summary-number {
        font-size: 28px;
        font-weight: 700;
        color: #2563eb;
        margin-bottom: 0.5rem;
      }

      p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
      }
    }
  }
}

.action-buttons {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;

  .btn {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;

    &.btn-primary {
      background: #2563eb;
      color: white;

      &:hover {
        background: #1e40af;
      }
    }

    &.btn-outline {
      background: white;
      border: 2px solid #e5e7eb;
      color: #374151;

      &:hover {
        border-color: #2563eb;
        color: #2563eb;
      }
    }
  }
}

.result-footer {
  text-align: center;
  padding-top: 2rem;
  border-top: 2px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  small {
    color: #9ca3af;
    font-size: 12px;
  }
}
</style>
```

---

## 🌐 STATE MANAGEMENT (Pinia)

### **exam.js Store**

```javascript
// stores/exam.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import API from '@/services/api'

export const useExamStore = defineStore('exam', () => {
  // State
  const sesiId = ref(null)
  const exam = ref(null)
  const questions = ref([])
  const answers = ref(new Map()) // sesi -> answers map
  const startTime = ref(null)
  const endTime = ref(null)
  const currentQuestionIndex = ref(0)

  // Computed
  const getRemainingSeconds = computed(() => {
    if (!endTime.value) return 0
    const now = new Date()
    const remaining = Math.floor((endTime.value - now) / 1000)
    return Math.max(0, remaining)
  })

  const currentQuestion = computed(() => questions.value[currentQuestionIndex.value])

  // Actions
  const enterExam = async (jadwalUjianId, token = null) => {
    try {
      const response = await API.post('/ujian/masuk', {
        jadwal_ujian_id: jadwalUjianId,
        token
      })

      sesiId.value = response.data.sesi_id
      exam.value = response.data
      startTime.value = new Date(response.data.waktu_mulai)
      endTime.value = new Date(exam.value.waktu_selesai)

      // Load first question
      await loadQuestion(1)

      return response.data
    } catch (error) {
      throw error
    }
  }

  const loadQuestion = async (questionNumber) => {
    try {
      const response = await API.get('/ujian/ambil-soal', {
        params: {
          sesi_id: sesiId.value,
          nomor_soal: questionNumber
        }
      })

      currentQuestionIndex.value = questionNumber - 1
      return response.data
    } catch (error) {
      throw error
    }
  }

  const saveAnswer = async (answerData) => {
    try {
      await API.post('/ujian/simpan-jawaban', {
        sesi_id: sesiId.value,
        ...answerData
      })

      // Store locally
      answers.value.set(`q_${currentQuestion.value.soal_hashid}`, answerData)
    } catch (error) {
      throw error
    }
  }

  const submitExam = async () => {
    try {
      const response = await API.post('/ujian/submit', {
        sesi_id: sesiId.value
      })

      return response.data
    } catch (error) {
      throw error
    }
  }

  const logEvent = async (eventType, eventData = {}) => {
    // Log session events (tab switch, offline, etc)
    try {
      await API.post('/ujian/log-event', {
        sesi_id: sesiId.value,
        event_type: eventType,
        event_data: eventData
      })
    } catch (error) {
      console.error('Error logging event:', error)
    }
  }

  return {
    // State
    sesiId,
    exam,
    questions,
    answers,
    currentQuestion,
    currentQuestionIndex,

    // Computed
    getRemainingSeconds,

    // Actions
    enterExam,
    loadQuestion,
    saveAnswer,
    submitExam,
    logEvent
  }
})
```

---

## 📱 PWA & OFFLINE CAPABILITIES

### **Service Worker (public/sw.js)**

```javascript
const CACHE_NAME = 'cbt-v1'
const urlsToCache = [
  '/',
  '/index.html',
  '/manifest.json',
  '/offline.html',
  '/assets/styles/main.css'
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cacheName) => cacheName !== CACHE_NAME)
          .map((cacheName) => caches.delete(cacheName))
      )
    }).then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (event) => {
  const { request } = event

  // Skip non-GET requests
  if (request.method !== 'GET') return

  // API requests: network first, fallback to cache
  if (request.url.includes('/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const responseClone = response.clone()
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseClone)
          })
          return response
        })
        .catch(() => caches.match(request))
    )
    return
  }

  // Static assets: cache first, fallback to network
  event.respondWith(
    caches.match(request)
      .then((response) => response || fetch(request))
      .catch(() => caches.match('/offline.html'))
  )
})
```

### **Manifest.json (PWA)**

```json
{
  "name": "CBT SMKN 1 Blora",
  "short_name": "CBT",
  "description": "Computer-Based Test System for SMKN 1 Blora",
  "start_url": "/ujian",
  "scope": "/",
  "display": "fullscreen",
  "orientation": "portrait-primary",
  "background_color": "#ffffff",
  "theme_color": "#2563eb",
  "categories": ["education"],
  "screenshots": [
    {
      "src": "/images/screenshot-1.png",
      "sizes": "1280x720",
      "type": "image/png"
    }
  ],
  "icons": [
    {
      "src": "/images/icon-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/images/icon-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ]
}
```

---

## 🎨 TAILWIND CONFIGURATION

```javascript
// tailwind.config.js
module.exports = {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        primary: '#2563eb',
        secondary: '#10b981',
        danger: '#ef4444',
        warning: '#f59e0b'
      },
      fontFamily: {
        sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'],
        mono: ['Roboto Mono', 'monospace']
      },
      spacing: {
        xs: '0.25rem',
        sm: '0.5rem',
        md: '1rem',
        lg: '1.5rem',
        xl: '2rem',
        '2xl': '3rem'
      }
    }
  },
  plugins: []
}
```

---

## 📱 MOBILE OPTIMIZATION

### **Exam Interface - Mobile Portrait**

```
┌────────────────────────┐
│ Timer | Soal 1/40      │
├────────────────────────┤
│ Soal:                  │
│ Berapa hasil 2+2?      │
│ [image if exists]      │
│                        │
│ A. ☐ Tiga             │
│ B. ☐ Empat            │
│ C. ☐ Lima             │
│ D. ☐ Enam             │
│                        │
│ < Sebelumnya  Lanjut > │
├────────────────────────┤
│ Soal: 1 2 3 4 5 6...  │
│ Terjawab: 12 / 40     │
└────────────────────────┘
```

---

## 🔒 SECURITY PRACTICES

1. **XSS Prevention**
   - Vue 3 auto-escapes template content
   - Use `v-text` for dynamic content
   - Never use `v-html` with user input

2. **CSRF Protection**
   - JWT tokens in Authorization header
   - No cookies used

3. **Data Encryption**
   - All API requests over HTTPS
   - Sensitive data (passwords) never stored locally

4. **IndexedDB Encryption** (Optional)
   - Use `TweetNaCl.js` for local answer encryption

---

## 📝 IMPLEMENTATION NOTES

1. **Component Library**: Consider `shadcn/vue` or `Headless UI` for accessible components
2. **Form Validation**: Use `VeeValidate` or `Zod`
3. **HTTP Interceptors**: Add token refresh logic in Axios
4. **Error Handling**: Implement global error boundary
5. **Performance**: Lazy load routes, optimize images with WebP

---

*Last Updated: June 2026*
*Status: Ready for Development*
# FRONTEND IMPLEMENTATION STATUS - CBT SMKN 1 BLORA

## Status Implementasi Saat Ini - 2026-06-04

Bagian ini adalah acuan frontend terbaru. Isi lama di bawah tetap disimpan sebagai rancangan awal/historis.

### Stack Aktual

Frontend production memakai:
- Vue 3 Composition API.
- Vue Router.
- Axios.
- Bootstrap 5 utility/component classes.
- Font Awesome icon classes.
- SweetAlert2 untuk dialog konfirmasi/notifikasi.
- Vite build di server production `/var/www/html`.

Route SPA utama ada di `resources/js/router/index.js`.

### Halaman Management Aktual

Menu sidebar utama memakai `resources/js/components/AdminSidebar.vue`.

Halaman management yang aktif:

| URL | Route Name | File Vue | Fungsi |
|---|---|---|---|
| `/vue/management/staff` | `StaffManagement` | `resources/js/pages/Management/StaffManagement.vue` | Manajemen staf |
| `/vue/management/siswa` | `StudentManagement` | `resources/js/pages/Management/StudentManagement.vue` | Manajemen siswa |
| `/vue/management/master` | `MasterDataManagement` | `resources/js/pages/Management/MasterDataManagement.vue` | Master sekolah |
| `/vue/management/soal` | `QuestionBank` | `resources/js/pages/Management/QuestionBank.vue` | Bank soal |
| `/vue/management/jadwal` | `ExamSchedule` | `resources/js/pages/Management/ExamSchedule.vue` | Jadwal ujian |
| `/vue/management/hasil` | `ExamResults` | `resources/js/pages/Management/ExamResults.vue` | Hasil ujian aktif/belum arsip |
| `/vue/management/download-hasil` | `ExamResultDownloads` | `resources/js/pages/Management/ExamResultDownloads.vue` | Preview/download PDF hasil belum arsip |
| `/vue/management/arsip-hasil` | `ExamResultArchives` | `resources/js/pages/Management/ExamResultArchives.vue` | Arsip hasil ujian yang sudah diarsipkan |
| `/vue/management/fingerprints` | `DeviceFingerprints` | `resources/js/pages/Management/DeviceFingerprints.vue` | Kunci perangkat/device lock |

### Hasil Ujian Aktif - `/vue/management/hasil`

File: `resources/js/pages/Management/ExamResults.vue`

Tujuan:
- Menampilkan hasil ujian yang belum diarsipkan.
- Tidak memuat seluruh tabel nilai langsung saat kelas dipilih.
- Mengurangi query berat ke server dengan dua tahap load.

Alur UI:
1. User memilih `Tingkat`.
2. User memilih `Jurusan`.
3. User memilih `Rombel`.
4. User klik `Tampilkan`.
5. UI menampilkan dropdown `Pilih Ujian`.
6. User memilih satu ujian.
7. User klik `Muat Hasil`.
8. Tabel hasil siswa baru dimuat.

Endpoint:
- `GET /kelola/data/hasil-ujian/options`
  - Memuat daftar kelas yang punya jadwal belum arsip.
- `GET /kelola/data/hasil-ujian?tingkat=...&jurusan_id=...&rombel_id=...`
  - Memuat data kelas dan daftar ujian saja.
  - Response schedule tidak membawa field `hasil`.
- `GET /kelola/data/hasil-ujian?tingkat=...&jurusan_id=...&rombel_id=...&jadwal_id=...`
  - Memuat detail hasil untuk satu ujian.
  - Response schedule membawa `statistik` dan `hasil`.

Data yang ditampilkan:
- Nama kelas.
- Jurusan.
- Rombel.
- Jumlah jadwal ujian.
- Judul ujian.
- Mata pelajaran.
- Waktu mulai dan selesai.
- Statistik: total target, sudah masuk, belum masuk, rata-rata.
- Tabel siswa: nama, NISN/username, status, nilai PG, nilai isian, total, ranking, waktu submit.

Aturan penting:
- Halaman ini hanya untuk `jadwal_ujians.diarsipkan_at IS NULL`.
- Jika semua jadwal sudah diarsipkan, halaman ini dapat kosong. Itu kondisi benar.

### Download Hasil - `/vue/management/download-hasil`

File: `resources/js/pages/Management/ExamResultDownloads.vue`

Tujuan:
- Memilih hasil ujian yang belum diarsipkan untuk preview/download PDF.
- Setelah semua kelas target suatu jadwal sudah diunduh, jadwal dapat diarsipkan lewat proses backend yang sudah tersedia di jadwal/download flow.

Endpoint:
- `GET /kelola/data/download-hasil/options`
- `GET /kelola/data/download-hasil/preview`
- `GET /kelola/data/download-hasil/download`

Aturan penting:
- Hanya menampilkan jadwal belum arsip.
- Backend memakai `whereNull('jadwal_ujians.diarsipkan_at')`.
- Catatan download disimpan di tabel `hasil_ujian_unduhans`.

### Arsip Hasil - `/vue/management/arsip-hasil`

File: `resources/js/pages/Management/ExamResultArchives.vue`

Tujuan:
- Menampilkan hasil ujian yang sudah diarsipkan.
- Memisahkan arsip dari halaman hasil aktif agar halaman aktif tetap ringan dan tidak membingungkan.

Alur UI:
1. User memilih `Tahun`.
2. User memilih `Tingkat`.
3. User memilih `Jurusan`.
4. User memilih `Rombel`.
5. User klik `Tampilkan`.
6. UI menampilkan dropdown daftar ujian arsip pada filter tersebut.
7. User memilih ujian.
8. User klik `Muat Arsip`.
9. Detail statistik dan tabel nilai siswa dimuat.

Endpoint:
- `GET /kelola/data/arsip-hasil/options`
  - Memuat `years` dan `classes`.
  - Hanya dari jadwal yang sudah diarsipkan.
- `GET /kelola/data/arsip-hasil?tahun=...&tingkat=...&jurusan_id=...&rombel_id=...`
  - Memuat data kelas dan daftar jadwal arsip saja.
  - Tidak membawa field `hasil`.
- `GET /kelola/data/arsip-hasil?tahun=...&tingkat=...&jurusan_id=...&rombel_id=...&jadwal_id=...`
  - Memuat detail satu arsip hasil ujian.

Aturan penting:
- Halaman ini hanya untuk `jadwal_ujians.diarsipkan_at IS NOT NULL`.
- Filter tahun berasal dari `extract(year from jadwal_ujians.waktu_mulai)`.
- Tombol PDF tidak dipakai di halaman arsip agar tidak berbenturan dengan endpoint download aktif yang memang hanya untuk belum arsip.

### Kunci Perangkat - `/vue/management/fingerprints`

File: `resources/js/pages/Management/DeviceFingerprints.vue`

Tujuan:
- Monitoring dan reset device lock siswa.
- Melihat sesi aktif, sesi terkunci, dan indikasi gawai ganda.
- Reset/unlock siswa jika pindah perangkat sah.

Frontend fingerprint:
- File util: `resources/js/utils/fingerprint.js`.
- Mengambil sinyal browser/device dan local storage anchor.
- Anchor token membantu membedakan perangkat dengan model HP yang sama.

Perilaku UI:
- Jika user terkunci, frontend diarahkan logout/login ulang.
- Admin dapat melakukan unlock/reset melalui halaman manajemen fingerprint.

### Pola UX Hasil dan Arsip

Pola yang dipakai untuk mengurangi beban server:
- Jangan render seluruh hasil semua ujian sekaligus.
- Pilih kelas terlebih dahulu.
- Tampilkan dropdown ujian.
- Query detail nilai hanya untuk satu ujian yang dipilih.

Pola ini berlaku pada:
- `ExamResults.vue`
- `ExamResultArchives.vue`

### Build dan Deployment Frontend

Build production:

```bash
cd /var/www/html
npm run build
sudo systemctl reload php8.3-fpm php8.2-fpm
```

Catatan:
- Build dilakukan di server Linux production.
- Build lokal Windows dapat gagal jika `node_modules/.bin/vite.cmd` tidak tersedia.
- Setelah build, asset baru berada di `public/build/assets`.

### Verifikasi Frontend

Smoke test tanpa login:
- `/vue/management/hasil` redirect login.
- `/vue/management/arsip-hasil` redirect login.
- `/login` status `200`.

Verifikasi build:
- `npm run build` sukses.
- `manifest.json` memuat chunk `ExamResults` dan `ExamResultArchives`.

---
