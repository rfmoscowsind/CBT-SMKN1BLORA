<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-fingerprint me-2 text-primary"></i>Kunci Perangkat
                    </h5>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
            
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-fingerprint text-primary me-2"></i> Manajemen Kunci Perangkat (Device Lock)
                    </h4>
                    <p class="text-muted small mb-0">Mencegah kecurangan ujian dengan membatasi satu gawai per siswa secara real-time.</p>
                </div>
                <button @click="fetchSessions" class="btn btn-primary shadow-sm" :disabled="loading">
                    <i class="fa-solid fa-rotate me-1" :class="{ 'fa-spin': loading }"></i> Segarkan Data
                </button>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h6 class="fw-bold mb-1">
                                <i class="fa-solid fa-shield-halved me-2" :class="deviceLockEnabled ? 'text-success' : 'text-warning'"></i>
                                Device Lock {{ deviceLockEnabled ? 'ON' : 'OFF' }}
                            </h6>
                            <div class="small text-muted">
                                {{ deviceLockEnabled
                                    ? 'Perubahan perangkat siswa akan diblokir dan dicatat.'
                                    : 'Siswa dapat pindah perangkat tanpa diblokir. Histori fingerprint tetap dicatat untuk audit.' }}
                            </div>
                        </div>
                        <div class="form-check form-switch fs-5 mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="deviceLockToggle"
                                v-model="deviceLockEnabled"
                                :disabled="deviceLockSaving"
                                @change="toggleDeviceLock"
                            >
                            <label class="form-check-label fw-semibold" for="deviceLockToggle">
                                {{ deviceLockSaving ? 'Menyimpan...' : (deviceLockEnabled ? 'Aktif' : 'Audit Only') }}
                            </label>
                        </div>
                    </div>
                    <div v-if="!deviceLockEnabled" class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Device Lock sedang OFF. Siswa tidak akan terkunci saat pindah perangkat, tetapi histori perangkat tetap tersimpan.
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card stat-card border-0 shadow-sm p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1 fw-bold">Sesi Aktif</h6>
                                <h3 class="fw-bold text-dark mb-0">{{ activeCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card stat-card border-0 shadow-sm p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1 fw-bold">Sesi Terkunci (Ganti Gawai)</h6>
                                <h3 class="fw-bold text-danger mb-0">{{ lockedCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card stat-card border-0 shadow-sm p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1 fw-bold">Terdeteksi Gawai Ganda (Shared)</h6>
                                <h3 class="fw-bold text-warning mb-0">{{ duplicateCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-dark bg-opacity-10 text-dark me-3">
                                <i class="fa-solid fa-cloud"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1 fw-bold">Indikasi Cloud Phone</h6>
                                <h3 class="fw-bold text-dark mb-0">{{ cloudPhoneCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input 
                                    type="text" 
                                    v-model="searchQuery" 
                                    class="form-control bg-light border-start-0 ps-0" 
                                    placeholder="Cari siswa atau username..."
                                >
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <select v-model="filterStatus" class="form-select bg-light">
                                <option value="all">Semua Status</option>
                                <option value="locked">Terkunci (Lock)</option>
                                <option value="unlocked">Normal (Active)</option>
                                <option value="duplicate">Gawai Ganda (Shared)</option>
                                <option value="cloud">Indikasi Cloud Phone</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <select v-model="filterKelas" class="form-select bg-light">
                                <option value="all">Semua Kelas</option>
                                <option v-for="k in kelasList" :key="k" :value="k">{{ k }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-md-end">
                            <button @click="resetFilters" class="btn btn-outline-secondary w-100">
                                Reset Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Table Section -->
            <div class="card border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-2 flex-wrap gap-2">
                    <div class="text-muted small">
                        <span v-if="totalFiltered > 0">
                            Menampilkan <strong>{{ (currentPage - 1) * perPage + 1 }}</strong>???<strong>{{ Math.min(currentPage * perPage, totalFiltered) }}</strong>
                            dari <strong>{{ totalFiltered }}</strong> data
                        </span>
                        <span v-else>Tidak ada data</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted small mb-0">Per halaman:</label>
                        <select class="form-select form-select-sm" style="width: 80px;" v-model.number="perPage" @change="currentPage = 1">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div v-if="filteredSessions.length === 0" class="text-center py-5">
                        <i class="fa-solid fa-fingerprint text-muted fs-1 mb-3"></i>
                        <h6 class="fw-bold text-dark">Tidak ada data ditemukan</h6>
                        <p class="text-muted small">Coba sesuaikan kata kunci pencarian atau filter status.</p>
                    </div>

                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="20%">Nama Siswa / Username</th>
                                    <th width="10%">Kelas</th>
                                    <th width="15%">Info Ujian / Mapel</th>
                                    <th width="18%">Fingerprint Hash</th>
                                    <th width="15%">Status / Gawai</th>
                                    <th width="12%">Risiko</th>
                                    <th width="15%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(s, index) in paginatedSessions" :key="s.id">
                                    <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ s.name }}</div>
                                        <div class="text-secondary small">{{ s.username }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1">
                                            {{ s.kelas || '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small text-primary">{{ s.mapel }}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Jadwal: {{ s.jadwal || '-' }}</div>
                                    </td>
                                    <td>
                                        <div v-if="s.device_fingerprint" class="d-flex align-items-center">
                                            <code class="text-dark bg-light px-2 py-1 rounded small border text-truncate" style="max-width: 140px;" :title="s.device_fingerprint">
                                                {{ s.device_fingerprint }}
                                            </code>
                                            <button @click="copyToClipboard(s.device_fingerprint)" class="btn btn-link btn-sm text-secondary p-0 ms-2" title="Salin hash">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                        <div v-else class="text-muted small italic">Belum terdaftar</div>
                                    </td>
                                    <td>
                                        <!-- Lock Status Badge -->
                                        <div class="mb-1">
                                            <span v-if="isLocked(s)" class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1">
                                                <i class="fa-solid fa-lock me-1"></i> TERKUNCI (MISMATCH)
                                            </span>
                                            <span v-else-if="isFpDuplicate(s.device_fingerprint)" class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i> GAWAI GANDA
                                            </span>
                                            <span v-else-if="s.device_fingerprint" class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                                                <i class="fa-solid fa-shield-halved me-1"></i> TERVERIFIKASI
                                            </span>
                                            <span v-else class="badge bg-light text-secondary border px-2 py-1">
                                                MENUNGGU LOGIN
                                            </span>
                                        </div>

                                        <!-- Device Short Info -->
                                        <div v-if="s.device_info" class="text-muted" style="font-size: 0.75rem;">
                                            <i class="fa-solid fa-laptop-code me-1"></i> 
                                            {{ parseUserAgent(s.device_info.user_agent) }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge px-2 py-1" :class="riskBadgeClass(s)">
                                            <i :class="riskIcon(s)" class="me-1"></i>{{ riskInfo(s).label }}
                                        </span>
                                        <div v-if="riskInfo(s).cloudPhoneSuspected" class="text-danger small mt-1">
                                            Cloud phone?
                                        </div>
                                        <div v-if="riskInfo(s).score > 0" class="text-muted small">
                                            Skor {{ riskInfo(s).score }}/100
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <!-- Lock/Unlock Action -->
                                            <button 
                                                v-if="isLocked(s)"
                                                @click="s.sesi_id ? confirmResumeSession(s) : confirmUnlock(s)" 
                                                class="btn btn-outline-success btn-sm px-3"
                                                title="Buka sesi agar siswa bisa melanjutkan tanpa kehilangan jawaban"
                                            >
                                                <i class="fa-solid fa-play me-1"></i> Lanjutkan
                                            </button>
                                            <button
                                                v-else-if="s.sesi_id && s.status !== 'selesai' && s.status !== 'reset'"
                                                @click="confirmResumeSession(s)"
                                                class="btn btn-outline-primary btn-sm px-3"
                                                title="Buka sesi tanpa menghapus jawaban"
                                            >
                                                <i class="fa-solid fa-play me-1"></i> Buka Sesi
                                            </button>
                                            <button 
                                                v-else-if="s.device_fingerprint"
                                                @click="confirmResetDevice(s)" 
                                                class="btn btn-outline-success btn-sm px-3"
                                                title="Reset kunci perangkat agar siswa bisa memakai gawai baru"
                                            >
                                                <i class="fa-solid fa-rotate-left me-1"></i> Reset Gawai
                                            </button>
                                            <button 
                                                v-if="!isLocked(s) && s.device_fingerprint"
                                                @click="confirmLock(s)" 
                                                class="btn btn-outline-danger btn-sm px-3"
                                                title="Kunci gawai/akses siswa"
                                            >
                                                <i class="fa-solid fa-lock me-1"></i> Kunci
                                            </button>
                                            <button
                                                v-if="s.sesi_id && s.status !== 'selesai'"
                                                @click="confirmResetExamSession(s)"
                                                class="btn btn-outline-danger btn-sm px-3"
                                                title="Reset ujian dari nol dan hapus jawaban"
                                            >
                                                <i class="fa-solid fa-trash-arrow-up me-1"></i> Reset Nol
                                            </button>
                                            
                                            <!-- View Details Modal Trigger -->
                                            <button 
                                                v-if="s.device_fingerprint_raw"
                                                @click="viewDetails(s)" 
                                                class="btn btn-outline-info btn-sm"
                                                title="Lihat spesifikasi teknis browser"
                                            >
                                                <i class="fa-solid fa-circle-info"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="d-flex justify-content-center align-items-center gap-1 py-3 border-top">
                        <button class="btn btn-sm btn-outline-secondary" :disabled="currentPage === 1" @click="currentPage = 1" title="Halaman pertama">
                            <i class="fa-solid fa-angles-left"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="currentPage === 1" @click="currentPage--" title="Sebelumnya">
                            <i class="fa-solid fa-angle-left"></i>
                        </button>
                        <template v-for="p in pageNumbers" :key="p">
                            <span v-if="p === '...'" class="px-2 text-muted">...</span>
                            <button
                                v-else
                                class="btn btn-sm"
                                :class="p === currentPage ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="currentPage = p"
                            >{{ p }}</button>
                        </template>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="currentPage === totalPages" @click="currentPage++" title="Berikutnya">
                            <i class="fa-solid fa-angle-right"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="currentPage === totalPages" @click="currentPage = totalPages" title="Halaman terakhir">
                            <i class="fa-solid fa-angles-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Details Modal -->
            <div class="modal fade" id="modalFingerprintDetails" tabindex="-1" aria-hidden="true" ref="modalDetails">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" v-if="selectedSession">
                        <div class="modal-header bg-dark text-white border-0 py-3">
                            <h5 class="modal-title fw-bold">
                                <i class="fa-solid fa-network-wired me-2 text-info"></i> Detail Fingerprint: {{ selectedSession.name }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4 bg-light">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="bg-white p-3 rounded shadow-sm border h-100">
                                        <h6 class="fw-bold border-bottom pb-2 text-secondary">Informasi Identitas</h6>
                                        <table class="table table-borderless table-sm mb-0 small">
                                            <tr>
                                                <td width="35%" class="fw-semibold">Nama Siswa:</td>
                                                <td>{{ selectedSession.name }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold">Username/NISN:</td>
                                                <td><code>{{ selectedSession.username }}</code></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold">Kelas:</td>
                                                <td>{{ selectedSession.kelas || '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold">Alamat IP:</td>
                                                <td><code>{{ selectedSession.ip_address || '-' }}</code></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="bg-white p-3 rounded shadow-sm border h-100">
                                        <h6 class="fw-bold border-bottom pb-2 text-secondary">Fingerprint Hash</h6>
                                        <div class="p-2 bg-light border rounded font-monospace small mb-2 text-break">
                                            {{ selectedSession.device_fingerprint }}
                                        </div>
                                        <small class="text-muted d-block leading-normal">
                                            Hash di atas didapat dari parameter hardware & browser di sebelah bawah. Nilai ini stabil untuk peramban yang sama.
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-12 mb-3" v-if="riskInfo(selectedSession).score > 0">
                                    <div class="p-3 rounded shadow-sm border" :class="riskPanelClass(selectedSession)">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h6 class="fw-bold mb-1">
                                                    <i :class="riskIcon(selectedSession)" class="me-2"></i>Analisis Risiko Perangkat
                                                </h6>
                                                <div class="small">{{ riskInfo(selectedSession).summary }}</div>
                                            </div>
                                            <span class="badge bg-dark">Skor {{ riskInfo(selectedSession).score }}/100</span>
                                        </div>
                                        <ul v-if="riskInfo(selectedSession).flags.length" class="small mb-0 mt-2 ps-3">
                                            <li v-for="flag in riskInfo(selectedSession).flags" :key="flag.code">{{ flag.message }}</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="bg-white p-3 rounded shadow-sm border">
                                        <h6 class="fw-bold border-bottom pb-2 text-secondary">Parameter Fingerprint Perangkat</h6>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm mb-0 small text-start">
                                                <tbody>
                                                    <tr v-for="row in fingerprintDetailRows" :key="row.label">
                                                        <th width="30%">{{ row.label }}</th>
                                                        <td class="text-wrap" :class="{ 'font-monospace': row.mono }">
                                                            {{ row.value }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button v-if="selectedSession.sesi_id && selectedSession.status !== 'selesai' && selectedSession.status !== 'reset'" @click="resumeDirect(selectedSession)" class="btn btn-primary">
                                <i class="fa-solid fa-play me-1"></i> Buka Sesi / Lanjutkan
                            </button>
                            <button v-if="selectedSession.device_fingerprint" @click="resetDeviceDirect(selectedSession.id)" class="btn btn-success">
                                <i class="fa-solid fa-rotate-left me-1"></i> Reset Gawai
                            </button>
                            <button v-if="!isLocked(selectedSession) && selectedSession.device_fingerprint" @click="lockDirect(selectedSession.id)" class="btn btn-danger">
                                <i class="fa-solid fa-lock me-1"></i> Kunci Akses Siswa
                            </button>
                            <button v-if="selectedSession.sesi_id && selectedSession.status !== 'selesai'" @click="confirmResetExamSession(selectedSession)" class="btn btn-outline-danger">
                                <i class="fa-solid fa-trash-arrow-up me-1"></i> Reset Ulang dari Nol
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import * as bootstrap from 'bootstrap';
import AdminSidebar from '../../components/AdminSidebar.vue';

const sessions = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterStatus = ref('all');
const filterKelas = ref('all');
const selectedSession = ref(null);
const modalDetails = ref(null);
const deviceLockEnabled = ref(true);
const deviceLockSaving = ref(false);
let modalInstance = null;

const fetchSessions = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/kelola/data/device-fingerprints', { headers: { 'Accept': 'application/json' } });
        if (response.data?.success) {
            sessions.value = response.data.data || [];
            deviceLockEnabled.value = Boolean(response.data.settings?.device_lock_enabled ?? true);
        } else {
            throw new Error('Endpoint tidak mengembalikan JSON fingerprint.');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Gagal', 'Gagal memuat data sesi gawai.', 'error');
    } finally {
        loading.value = false;
    }
};

const toggleDeviceLock = async () => {
    const target = Boolean(deviceLockEnabled.value);
    deviceLockSaving.value = true;
    try {
        const response = await axios.post('/kelola/data/device-lock/toggle', { enabled: target }, { headers: { 'Accept': 'application/json' } });
        deviceLockEnabled.value = Boolean(response.data.device_lock_enabled);
        Swal.fire('Berhasil', response.data.message, 'success');
    } catch (e) {
        console.error(e);
        deviceLockEnabled.value = !target;
        Swal.fire('Gagal', 'Gagal mengubah status Device Lock.', 'error');
    } finally {
        deviceLockSaving.value = false;
    }
};

const isLocked = (session) => {
    if (!session) return false;
    return Boolean(session.is_device_locked)
        || Boolean(session.session_is_device_locked)
        || session.status === 'terkunci';
};

const activeCount = computed(() => sessions.value.filter(s => ['aktif', 'terkunci'].includes(s.status)).length);

const lockedCount = computed(() => sessions.value.filter(s => isLocked(s)).length);

// Count of sessions sharing a fingerprint with another user
const duplicateCount = computed(() => {
    const counts = {};
    sessions.value.forEach(s => {
        if (s.device_fingerprint) {
            counts[s.device_fingerprint] = (counts[s.device_fingerprint] || 0) + 1;
        }
    });
    return sessions.value.filter(s => s.device_fingerprint && counts[s.device_fingerprint] > 1).length;
});

const cloudPhoneCount = computed(() => sessions.value.filter(s => riskInfo(s).cloudPhoneSuspected).length);

const kelasList = computed(() => {
    const list = sessions.value.map(s => s.kelas).filter(Boolean);
    return [...new Set(list)].sort();
});

const isFpDuplicate = (fp) => {
    if (!fp) return false;
    const count = sessions.value.filter(s => s.device_fingerprint === fp).length;
    return count > 1;
};

const formatBoolean = (value) => {
    if (value === true) return 'Ya';
    if (value === false) return 'Tidak';
    return '-';
};

const calculateRiskFromRaw = (raw = {}) => {
    const flags = [];
    let score = 0;
    const add = (weight, code, message, cloudSignal = false) => {
        score += weight;
        flags.push({ code, weight, message, cloudSignal });
    };

    const ua = String(raw.userAgent || '').toLowerCase();
    const renderer = `${raw.webglVendor || ''} ${raw.webglRenderer || ''}`.toLowerCase();
    const isMobileUa = /android|iphone|ipad|ipod|mobile/.test(ua);
    const isAndroid = /android/.test(ua);
    const isLikelyPhone = raw.deviceType === 'HP' || isMobileUa || raw.mobileHint === true;
    const virtualRenderer = /(swiftshader|llvmpipe|virtualbox|vmware|parallels|virgl|qemu|emulator|android emulator|software rasterizer|mesa offscreen|google swiftshader)/i;

    if (virtualRenderer.test(renderer)) add(45, 'virtual_webgl', 'WebGL renderer terlihat virtual/software renderer.', true);
    if ((raw.webglRenderer === 'unsupported' || raw.webglRenderer === 'blocked') && isLikelyPhone) add(18, 'webgl_missing_mobile', 'WebGL tidak tersedia/terblokir pada perangkat yang mengaku mobile.', true);
    if (isLikelyPhone && (!raw.touchSupport || Number(raw.maxTouchPoints || 0) === 0)) add(35, 'mobile_without_touch', 'User agent mobile/HP tetapi touch support tidak terdeteksi.', true);
    if (isLikelyPhone && raw.pointerCoarse === false) add(18, 'mobile_without_coarse_pointer', 'Perangkat mobile tetapi pointer kasar/touch utama tidak terdeteksi.', true);
    if (isLikelyPhone && raw.hoverNone === false) add(10, 'mobile_with_hover', 'Perangkat mobile terdeteksi punya hover seperti desktop.', true);
    if (isAndroid && raw.mobileHint === false) add(20, 'android_mobile_hint_false', 'User agent Android tetapi browser mobile hint bernilai false.', true);
    if (isLikelyPhone && Number(raw.devicePixelRatio || 0) <= 1) add(12, 'low_mobile_dpr', 'Pixel ratio rendah untuk perangkat HP modern.', false);
    if (isLikelyPhone && Number(raw.hardwareConcurrency || 0) > 16) add(16, 'too_many_mobile_cores', 'Jumlah CPU core tidak lazim untuk HP peserta.', true);
    if (isLikelyPhone && (raw.hasDeviceMotion === false || raw.hasDeviceOrientation === false)) add(10, 'mobile_sensor_api_missing', 'API motion/orientation tidak tersedia pada perangkat yang mengaku HP.', false);
    if (isLikelyPhone && raw.hasVibration === false) add(8, 'mobile_vibration_missing', 'API vibration tidak tersedia pada perangkat yang mengaku HP.', false);
    if (raw.timezone && raw.timezone !== 'Asia/Jakarta') add(8, 'timezone_outside_jakarta', 'Zona waktu perangkat bukan Asia/Jakarta.', false);
    if (Array.isArray(raw.languages) && raw.languages.length === 0) add(6, 'languages_empty', 'Daftar bahasa browser kosong.', false);
    if (isLikelyPhone && raw.connectionType === 'unknown' && raw.connectionDownlink === 'unknown') add(6, 'connection_info_hidden', 'Informasi koneksi tidak tersedia.', false);
    if (/headless|phantomjs|selenium|webdriver/.test(ua)) add(60, 'automation_user_agent', 'User agent mengandung indikator automation/headless.', true);

    const cloudPhoneSignals = flags.filter(flag => flag.cloudSignal).map(flag => flag.code);
    const cappedScore = Math.min(score, 100);

    return {
        score: cappedScore,
        level: cappedScore >= 60 ? 'high' : cappedScore >= 30 ? 'medium' : cappedScore >= 12 ? 'low' : 'normal',
        flags,
        cloudPhoneSuspected: cappedScore >= 45 || cloudPhoneSignals.length >= 2,
        cloudPhoneSignals
    };
};

const riskInfo = (session) => {
    const raw = session?.device_fingerprint_raw || {};
    const calculated = calculateRiskFromRaw(raw);
    const score = Number(raw.riskScore ?? calculated.score ?? 0);
    const flags = Array.isArray(raw.riskFlags) && raw.riskFlags.length ? raw.riskFlags : calculated.flags;
    const cloudPhoneSuspected = Boolean(raw.cloudPhoneSuspected ?? calculated.cloudPhoneSuspected);
    const level = raw.riskLevel || calculated.level;
    const label = {
        high: 'Tinggi',
        medium: 'Sedang',
        low: 'Rendah',
        normal: 'Normal'
    }[level] || 'Normal';
    const summary = cloudPhoneSuspected
        ? 'Ada indikasi environment cloud phone/emulator/remote device. Perlu verifikasi manual sebelum dibuka.'
        : score > 0
            ? 'Ada beberapa sinyal tidak lazim, tetapi belum cukup kuat sebagai indikasi cloud phone.'
            : 'Tidak ada indikator cloud phone yang kuat dari data browser.';

    return { score, flags, cloudPhoneSuspected, level, label, summary };
};

const riskBadgeClass = (session) => {
    const info = riskInfo(session);
    if (info.cloudPhoneSuspected || info.level === 'high') return 'bg-danger bg-opacity-10 text-danger border border-danger';
    if (info.level === 'medium') return 'bg-warning bg-opacity-10 text-warning border border-warning';
    if (info.level === 'low') return 'bg-info bg-opacity-10 text-info border border-info';
    return 'bg-success bg-opacity-10 text-success border border-success';
};

const riskPanelClass = (session) => {
    const info = riskInfo(session);
    if (info.cloudPhoneSuspected || info.level === 'high') return 'bg-danger bg-opacity-10 border-danger text-danger';
    if (info.level === 'medium') return 'bg-warning bg-opacity-10 border-warning';
    return 'bg-info bg-opacity-10 border-info';
};

const riskIcon = (session) => {
    const info = riskInfo(session);
    if (info.cloudPhoneSuspected || info.level === 'high') return 'fa-solid fa-cloud-bolt';
    if (info.level === 'medium') return 'fa-solid fa-triangle-exclamation';
    if (info.level === 'low') return 'fa-solid fa-circle-info';
    return 'fa-solid fa-shield-halved';
};

const getRaw = (key, fallback = '-') => selectedSession.value?.device_fingerprint_raw?.[key] ?? fallback;

const fingerprintDetailRows = computed(() => {
    const raw = selectedSession.value?.device_fingerprint_raw || {};
    const risk = riskInfo(selectedSession.value);
    const brands = Array.isArray(raw.userAgentBrands)
        ? raw.userAgentBrands.map(item => `${item.brand} ${item.version}`).join(', ')
        : '-';
    const languages = Array.isArray(raw.languages) ? raw.languages.join(', ') : '-';
    const riskFlags = risk.flags.length ? risk.flags.map(flag => flag.message).join(' | ') : '-';

    return [
        { label: 'Skor Risiko', value: `${risk.label} (${risk.score}/100)`, mono: false },
        { label: 'Indikasi Cloud Phone', value: formatBoolean(risk.cloudPhoneSuspected), mono: false },
        { label: 'Alasan Risiko', value: riskFlags, mono: false },
        { label: 'Local Storage Anchor', value: getRaw('localStorageAnchor'), mono: true },
        { label: 'Sumber Anchor', value: `${getRaw('localStorageAnchorSource')} / tersedia: ${formatBoolean(raw.localStorageAnchorAvailable)}`, mono: false },
        { label: 'Legacy Hash', value: getRaw('legacyHash'), mono: true },
        { label: 'Jenis Perangkat', value: getRaw('deviceType'), mono: false },
        { label: 'Sistem Operasi', value: `${getRaw('osName')} ${getRaw('osVersion', '')}`.trim(), mono: false },
        { label: 'Browser', value: `${getRaw('browserName')} ${getRaw('browserVersion', '')}`.trim(), mono: false },
        { label: 'User Agent', value: getRaw('userAgent'), mono: true },
        { label: 'Platform / Vendor', value: `${getRaw('platform')} / ${getRaw('vendor')}`, mono: false },
        { label: 'UA Brands', value: brands, mono: false },
        { label: 'Mobile Hint', value: formatBoolean(raw.mobileHint), mono: false },
        { label: 'Resolusi Layar', value: getRaw('screenRes'), mono: false },
        { label: 'Area Layar Tersedia', value: getRaw('availableScreenRes'), mono: false },
        { label: 'Viewport Saat Login', value: getRaw('viewport'), mono: false },
        { label: 'Pixel Ratio', value: getRaw('devicePixelRatio'), mono: false },
        { label: 'Color / Pixel Depth', value: `${getRaw('colorDepth')} / ${getRaw('pixelDepth')}`, mono: false },
        { label: 'Orientasi', value: getRaw('orientation'), mono: false },
        { label: 'Touch Support', value: `${formatBoolean(raw.touchSupport)} (${getRaw('maxTouchPoints', 0)} titik sentuh)`, mono: false },
        { label: 'Pointer / Hover', value: `pointer coarse: ${formatBoolean(raw.pointerCoarse)}, hover none: ${formatBoolean(raw.hoverNone)}`, mono: false },
        { label: 'Sensor Motion/Orientation', value: `${formatBoolean(raw.hasDeviceMotion)} / ${formatBoolean(raw.hasDeviceOrientation)}`, mono: false },
        { label: 'Vibration API', value: formatBoolean(raw.hasVibration), mono: false },
        { label: 'CPU Cores', value: getRaw('hardwareConcurrency'), mono: false },
        { label: 'Device Memory', value: `${getRaw('deviceMemory')} GB`, mono: false },
        { label: 'Bahasa', value: `${getRaw('language')} (${languages})`, mono: false },
        { label: 'Zona Waktu', value: getRaw('timezone'), mono: false },
        { label: 'Cookie Aktif', value: formatBoolean(raw.cookiesEnabled), mono: false },
        { label: 'Do Not Track', value: getRaw('doNotTrack'), mono: false },
        { label: 'Status Online', value: formatBoolean(raw.online), mono: false },
        { label: 'Koneksi', value: `${getRaw('connectionType')} / downlink ${getRaw('connectionDownlink')} Mbps / RTT ${getRaw('connectionRtt')} ms`, mono: false },
        { label: 'WebGL Vendor', value: getRaw('webglVendor'), mono: false },
        { label: 'WebGL Renderer', value: getRaw('webglRenderer'), mono: false },
        { label: 'WebGL Hash', value: getRaw('webglHash'), mono: true },
        { label: 'Canvas Hash', value: getRaw('canvasHash'), mono: true },
        { label: 'Waktu Ambil Data', value: getRaw('collectedAt'), mono: false },
    ];
});

const filteredSessions = computed(() => {
    return sessions.value.filter(s => {
        // 1. Search Query
        const matchSearch = 
            s.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            s.username.toLowerCase().includes(searchQuery.value.toLowerCase());
            
        // 2. Class Filter
        const matchKelas = filterKelas.value === 'all' || s.kelas === filterKelas.value;
        
        // 3. Status Filter
        let matchStatus = true;
        if (filterStatus.value === 'locked') {
            matchStatus = isLocked(s);
        } else if (filterStatus.value === 'unlocked') {
            matchStatus = !isLocked(s) && s.device_fingerprint;
        } else if (filterStatus.value === 'duplicate') {
            matchStatus = isFpDuplicate(s.device_fingerprint);
        } else if (filterStatus.value === 'cloud') {
            matchStatus = riskInfo(s).cloudPhoneSuspected;
        }
        
        return matchSearch && matchKelas && matchStatus;
    });
});

const resetFilters = () => {
    searchQuery.value = '';
    filterStatus.value = 'all';
    filterKelas.value = 'all';
    currentPage.value = 1;
};

// ?????? Pagination ????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
const currentPage = ref(1);
const perPage = ref(25);

const totalFiltered = computed(() => filteredSessions.value.length);
const totalPages = computed(() => Math.ceil(totalFiltered.value / perPage.value));

const paginatedSessions = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredSessions.value.slice(start, start + perPage.value);
});

const pageNumbers = computed(() => {
    const pages = [];
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        pages.push(1);
        if (current > 3) pages.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
        if (current < total - 2) pages.push('...');
        pages.push(total);
    }
    return pages;
});

watch([searchQuery, filterStatus, filterKelas], () => { currentPage.value = 1; });

const confirmUnlock = (session) => {
    Swal.fire({
        title: 'Buka Kunci Perangkat?',
        text: `Membuka kunci gawai untuk siswa ${session.name}. Siswa akan dapat masuk kembali menggunakan gawai baru.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Buka Kunci',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            unlockSession(session.id);
        }
    });
};

const confirmResetDevice = (session) => {
    Swal.fire({
        title: 'Reset Gawai Siswa?',
        text: `Menghapus kunci gawai lama untuk ${session.name}. Login berikutnya akan menerima perangkat yang dipakai siswa sebagai gawai baru.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Reset Gawai',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            unlockSession(session.id);
        }
    });
};

const confirmResumeSession = (session) => {
    Swal.fire({
        title: 'Buka Sesi / Lanjutkan?',
        text: `Membuka sesi ${session.name} tanpa menghapus jawaban. Waktu ujian tetap dihitung dari waktu mulai awal.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            resumeSession(session);
        }
    });
};

const resumeSession = async (session) => {
    if (!session?.sesi_id) return;
    try {
        const response = await axios.post(`/kelola/sesi/${session.sesi_id}/unlock-resume`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data.success) {
            Swal.fire('Berhasil', response.data.message, 'success');
            fetchSessions();
            if (modalInstance) modalInstance.hide();
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Gagal', e.response?.data?.message || 'Gagal membuka sesi siswa.', 'error');
    }
};

const resumeDirect = (session) => {
    resumeSession(session);
};

const confirmResetExamSession = (session) => {
    Swal.fire({
        title: 'Reset Ulang dari Nol?',
        text: `PERINGATAN: Jawaban ${session.name} akan dihapus dan siswa mengulang dari awal.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Reset dari Nol',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            resetExamSession(session);
        }
    });
};

const resetExamSession = async (session) => {
    if (!session?.sesi_id) return;
    try {
        const response = await axios.post(`/kelola/sesi/${session.sesi_id}/reset`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data.success) {
            Swal.fire('Berhasil', response.data.message, 'success');
            fetchSessions();
            if (modalInstance) modalInstance.hide();
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Gagal', e.response?.data?.message || 'Gagal reset ulang sesi.', 'error');
    }
};

const unlockSession = async (id) => {
    try {
        const response = await axios.post(`/kelola/data/device-fingerprints/${id}/unlock`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data.success) {
            Swal.fire('Berhasil', response.data.message, 'success');
            fetchSessions();
            if (modalInstance) modalInstance.hide();
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Gagal', 'Terjadi kesalahan saat me-reset kunci gawai.', 'error');
    }
};

const unlockDirect = (id) => {
    unlockSession(id);
};

const resetDeviceDirect = (id) => {
    unlockSession(id);
};

const confirmLock = (session) => {
    Swal.fire({
        title: 'Kunci Perangkat/Akses Siswa?',
        text: `Mengunci akses untuk siswa ${session.name}. Siswa akan diblokir dari ujian sampai dibuka kembali oleh admin.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Kunci Akses',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            lockSession(session.id);
        }
    });
};

const lockSession = async (id) => {
    try {
        const response = await axios.post(`/kelola/data/device-fingerprints/${id}/lock`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data.success) {
            Swal.fire('Berhasil', response.data.message, 'success');
            fetchSessions();
            if (modalInstance) modalInstance.hide();
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Gagal', 'Terjadi kesalahan saat mengunci perangkat siswa.', 'error');
    }
};

const lockDirect = (id) => {
    lockSession(id);
};

const viewDetails = (session) => {
    selectedSession.value = session;
    if (!modalInstance && modalDetails.value) {
        modalInstance = new bootstrap.Modal(modalDetails.value);
    }
    modalInstance.show();
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    Swal.fire({
        title: 'Disalin!',
        text: 'Hash fingerprint berhasil disalin ke clipboard.',
        icon: 'success',
        toast: true,
        position: 'top-end',
        timer: 1500,
        showConfirmButton: false
    });
};

const parseUserAgent = (ua) => {
    if (!ua) return '-';
    // Simplified parser for common browsers/OS
    let os = 'OS Lain';
    if (ua.includes('Windows')) os = 'Windows';
    else if (ua.includes('Android')) os = 'Android';
    else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';
    else if (ua.includes('Macintosh')) os = 'MacOS';
    else if (ua.includes('Linux')) os = 'Linux';
    
    let browser = 'Browser Lain';
    if (ua.includes('Chrome')) browser = 'Chrome';
    else if (ua.includes('Firefox')) browser = 'Firefox';
    else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'Safari';
    else if (ua.includes('Edge')) browser = 'Edge';
    
    return `${os} (${browser})`;
};

onMounted(() => {
    fetchSessions();
});
const logout = async () => {
    const result = await Swal.fire({
        title: 'Keluar?',
        text: 'Anda akan keluar dari sistem.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Keluar',
        cancelButtonText: 'Batal',
    });
    if (result.isConfirmed) window.location.href = '/logout';
};
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }
.fingerprint-container {
    background-color: #f8fafc;
    min-height: 90vh;
}
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-radius: 12px;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important;
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.table {
    font-size: 0.9rem;
}
.table code {
    font-size: 0.8rem;
}
.btn-sm {
    padding: 0.25rem 0.6rem;
    font-size: 0.8rem;
    border-radius: 6px;
}
.modal-content {
    border-radius: 16px;
    overflow: hidden;
    border: none;
}
</style>

