<template>
    <div class="exam-wrapper">
        <div class="exam-topbar">
            <div class="container-fluid px-4">
                <div class="row align-items-center">
                    <div class="col-4 d-none d-md-block">
                        <h5 class="mb-0 fw-bold text-dark">{{ identitasUjian.mapel }}</h5>
                        <small class="text-secondary">{{ identitasUjian.judul }}</small>
                    </div>
                    <div class="col-12 col-md-4 text-center d-flex align-items-center justify-content-center gap-2">
                        <div class="timer-box" :class="{ 'warning': isTimeWarning }">
                            <i class="fa-regular fa-clock me-1"></i> {{ formattedTime }}
                        </div>
                        <div class="d-flex align-items-center">
                            <span v-if="isOnline" class="badge bg-success rounded-pill px-2 py-1" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-wifi me-1"></i><span class="d-none d-sm-inline">Terhubung</span>
                            </span>
                            <span v-else class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-wifi-slash me-1"></i><span class="d-none d-sm-inline">Terputus</span>
                            </span>
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

        <div v-else-if="currentSoal" class="container-fluid px-4 py-4">
            <div class="row">
                
                <div class="col-lg-8 mb-4">
                    <div class="question-card p-4 d-flex flex-column">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <span class="question-number-badge">Soal Nomor {{ currentNomor }}</span>
                            <span class="badge bg-secondary">{{ currentSoal.tipe === 'PG' ? 'Pilihan Ganda' : 'Isian Singkat' }}</span>
                        </div>

                        <div class="mb-4">
                            <div class="question-text mb-3" v-html="currentSoal.pertanyaan"></div>
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
                                    <span v-html="opsi.teks"></span>
                                </label>
                            </div>
                        </div>

                        <div v-else-if="currentSoal.tipe === 'ISIAN'" class="flex-grow-1">
                            <textarea 
                                class="form-control" 
                                rows="5" 
                                v-model="currentSoal.jawaban_siswa"
                                placeholder="Ketik jawaban Anda di sini..."
                                @input="simpanJawabanLokal(currentSoal)"
                                @blur="simpanJawaban(currentSoal)"
                                :disabled="isSaving"
                            ></textarea>
                        </div>

                        <div class="d-flex justify-content-between mt-5 pt-3 border-top align-items-center">
                            <button class="btn btn-outline-secondary btn-navigation" @click="prevSoal" :disabled="currentNomor <= 1 || isFetchingSoal">
                                <i class="fa-solid fa-chevron-left me-sm-1"></i> <span class="d-none d-sm-inline">Sebelumnya</span>
                            </button>
                            
                            <div class="form-check form-switch fs-5 mb-0">
                                <input class="form-check-input" type="checkbox" id="raguCheck" v-model="currentSoal.ragu" @change="simpanJawaban(currentSoal)" :disabled="isSaving">
                                <label class="form-check-label text-warning fw-bold ms-2" for="raguCheck" style="cursor: pointer;">
                                    <span class="d-none d-sm-inline">Ragu-Ragu</span>
                                    <span class="d-inline d-sm-none" style="font-size: 0.9rem;">Ragu</span>
                                </label>
                            </div>
                            
                            <button v-if="currentNomor < totalSoal" class="btn btn-primary btn-navigation" @click="nextSoal">
                                <span class="d-none d-sm-inline">Selanjutnya</span> <i class="fa-solid fa-chevron-right ms-sm-1"></i>
                            </button>

                            <button v-else class="btn btn-success btn-navigation" @click="bukaModalKonfirmasi">
                                <i class="fa-solid fa-flag-checkered me-sm-1"></i> <span class="d-none d-sm-inline">Selesai Ujian</span><span class="d-inline d-sm-none">Selesai</span>
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
            <div v-else-if="isSyncing || pendingCount > 0" class="badge bg-warning text-dark p-3 shadow-lg fs-6 rounded-pill">
                <i class="fa-solid fa-rotate me-2" :class="{ 'fa-spin': isSyncing }"></i>
                {{ isSyncing ? 'Menyinkronkan jawaban...' : `${pendingCount} jawaban menunggu sinkron` }}
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
import { generateDeviceFingerprint } from '../../utils/fingerprint';

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
const identitasUjian = ref({});
const abjad = ['A', 'B', 'C', 'D', 'E'];

const isOnline = ref(navigator.onLine);
const isSaving = ref(false);
const isSubmitting = ref(false);
const isLoading = ref(true);
const isSyncing = ref(false);
const pendingAnswers = ref({});
const deviceFp = ref('');

let debounceTimer = null;
let modalKonfirmasiInstance = null;
let syncPromise = null;

const sisaWaktuDetik = ref(0); 
let intervalTimer = null;
let timerDeadlineMs = null;
let STORAGE_KEY = '';
let PENDING_STORAGE_KEY = '';

const forceLoginAgain = () => {
    Swal.fire({
        title: 'Ujian Terkunci',
        text: 'Akun terkunci karena terdeteksi perangkat berbeda. Silakan login ulang setelah admin membuka kunci perangkat.',
        icon: 'error',
        confirmButtonText: 'Login Ulang',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = '/login';
    });
};

const jumlahKosong   = computed(() => navigasi.value.filter(n => !n.terjawab).length);
const jumlahTerjawab = computed(() => navigasi.value.filter(n => n.terjawab).length);
const pendingCount = computed(() => Object.keys(pendingAnswers.value).length);
const hasAnswer = (value) => value !== null && value !== undefined && String(value).trim() !== '';
const updateNavigationState = (nomor, jawaban, ragu = null) => {
    const nav = navigasi.value.find(n => Number(n.nomor) === Number(nomor));
    if (!nav) return;
    nav.terjawab = hasAnswer(jawaban);
    if (ragu !== null && ragu !== undefined) {
        nav.ragu = Boolean(ragu);
    }
};
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
            headers: { 
                'Accept': 'application/json',
                'X-Device-Fingerprint': deviceFp.value
            }
        });

        if (res.data.redirect || res.data.status === 'selesai') {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
            return;
        }

        identitasUjian.value   = res.data.identitas;
        siswa.value       = res.data.siswa;
        navigasi.value    = res.data.navigasi;   // [{nomor, terjawab, ragu}]
        totalSoal.value   = res.data.total_soal;
        applyPendingNavigationState();

        // Sync timer dari server saat init
        syncTimerFromServer(res.data.sisa_detik, { force: true });
        startTimer();

        // Muat soal pertama
        await fetchSoal(1);
    } catch (e) {
        if (e.response?.status === 423) {
            forceLoginAgain();
            return;
        }
        console.error('Gagal memuat data ujian', e);
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
            params: { 
                nomor
            },
            headers: { 
                'Accept': 'application/json',
                'X-Device-Fingerprint': deviceFp.value
            }
        });

        if (res.data.redirect || res.data.status === 'selesai') {
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
            return;
        }

        // Sync sisa waktu dari server dengan toleransi agar tampilan tidak flicker.
        syncTimerFromServer(res.data.sisa_detik);

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
        updateNavigationState(nomor, res.data.jawaban_siswa, res.data.ragu);

        // Restore jawaban dari localStorage jika server tidak punya (offline recovery)
        muatJawabanLokal(nomor);
    } catch (e) {
        if (e.response?.status === 410) {
            // Waktu habis ??? server sudah auto-submit
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        } else if (e.response?.status === 423) {
            forceLoginAgain();
        } else {
            console.error('Gagal memuat soal', e);
        }
    } finally {
        isFetchingSoal.value = false;
    }
};


const persistPendingAnswers = () => {
    if (!PENDING_STORAGE_KEY) return;
    localStorage.setItem(PENDING_STORAGE_KEY, JSON.stringify(pendingAnswers.value));
};

const loadPendingAnswers = () => {
    if (!PENDING_STORAGE_KEY) return;

    try {
        const raw = localStorage.getItem(PENDING_STORAGE_KEY);
        pendingAnswers.value = raw ? (JSON.parse(raw) || {}) : {};
    } catch (e) {
        console.error('Gagal memuat antrean jawaban lokal', e);
        pendingAnswers.value = {};
    }
};

const applyPendingNavigationState = () => {
    Object.values(pendingAnswers.value).forEach((payload) => {
        if (!payload?.nomor) return;
        updateNavigationState(payload.nomor, payload.jawaban_siswa, payload.ragu);
    });
};

const buildPendingPayload = (soal, clientUpdatedAt = new Date().toISOString()) => ({
    nomor: soal.nomor || currentNomor.value,
    soal_hash: soal.hash_id,
    tipe: soal.tipe,
    opsi_hash: soal.tipe === 'PG' ? (soal.jawaban_siswa || null) : null,
    essay: soal.tipe === 'ISIAN' ? (soal.jawaban_siswa || null) : null,
    jawaban_siswa: soal.jawaban_siswa,
    ragu: Boolean(soal.ragu),
    client_updated_at: clientUpdatedAt,
    sync_status: 'pending',
    retry_count: Number(pendingAnswers.value[soal.nomor || currentNomor.value]?.retry_count || 0),
});

const legacyAnswerKey = (nomor) => `${STORAGE_KEY}_${nomor}`;

const removeLocalAnswer = (nomor) => {
    localStorage.removeItem(legacyAnswerKey(nomor));
    localStorage.removeItem(`_${nomor}`);
};

const clearPendingAnswer = (payload) => {
    const current = pendingAnswers.value[payload.nomor];
    if (current && current.client_updated_at !== payload.client_updated_at) {
        return;
    }

    const next = { ...pendingAnswers.value };
    delete next[payload.nomor];
    pendingAnswers.value = next;
    persistPendingAnswers();
    removeLocalAnswer(payload.nomor);
};

const markPendingFailed = (payloads) => {
    const next = { ...pendingAnswers.value };

    payloads.forEach((payload) => {
        const current = next[payload.nomor];
        if (!current || current.client_updated_at !== payload.client_updated_at) {
            return;
        }

        next[payload.nomor] = {
            ...current,
            sync_status: 'failed',
            retry_count: Number(current.retry_count || 0) + 1,
        };
    });

    pendingAnswers.value = next;
    persistPendingAnswers();
};

const simpanJawabanLokal = (soal = currentSoal.value, clientUpdatedAt = new Date().toISOString()) => {
    if (!soal || !currentNomor.value) return null;
    const nomor = soal.nomor || currentNomor.value;
    const payload = buildPendingPayload({ ...soal, nomor }, clientUpdatedAt);
    const key = legacyAnswerKey(nomor);
    localStorage.setItem(key, JSON.stringify({
        jawaban_siswa: soal.jawaban_siswa,
        ragu: soal.ragu,
        client_updated_at: payload.client_updated_at,
    }));

    pendingAnswers.value = {
        ...pendingAnswers.value,
        [nomor]: payload,
    };
    persistPendingAnswers();
    updateNavigationState(nomor, soal.jawaban_siswa, soal.ragu);

    return payload;
};

const muatJawabanLokal = (nomor) => {
    if (!currentSoal.value) return;
    const pending = pendingAnswers.value[nomor];
    if (pending) {
        currentSoal.value.jawaban_siswa = pending.jawaban_siswa;
        currentSoal.value.ragu = pending.ragu;
        updateNavigationState(nomor, currentSoal.value.jawaban_siswa, currentSoal.value.ragu);
        return;
    }

    const key = legacyAnswerKey(nomor);
    const data = localStorage.getItem(key);
    if (data) {
        try {
            const parsed = JSON.parse(data);
            if (parsed) {
                if (parsed.jawaban_siswa && !currentSoal.value.jawaban_siswa) {
                    currentSoal.value.jawaban_siswa = parsed.jawaban_siswa;
                }
                if (parsed.ragu !== undefined) {
                    currentSoal.value.ragu = parsed.ragu;
                }
                updateNavigationState(nomor, currentSoal.value.jawaban_siswa, currentSoal.value.ragu);

                if (currentSoal.value.jawaban_siswa || currentSoal.value.ragu) {
                    simpanJawabanLokal(currentSoal.value, parsed.client_updated_at || new Date().toISOString());
                }
            }
        } catch (e) {
            console.error('Gagal memuat jawaban lokal', e);
        }
    }
};

const syncTimerFromServer = (seconds, { force = false } = {}) => {
    const nextSeconds = Math.max(0, Number(seconds) || 0);
    if (force || timerDeadlineMs === null || Math.abs(sisaWaktuDetik.value - nextSeconds) > 5) {
        sisaWaktuDetik.value = nextSeconds;
        timerDeadlineMs = Date.now() + (nextSeconds * 1000);
    }
};

const tickTimer = () => {
    if (timerDeadlineMs === null) return;

    const remaining = Math.max(0, Math.ceil((timerDeadlineMs - Date.now()) / 1000));
    sisaWaktuDetik.value = remaining;

    if (remaining <= 0) {
        clearInterval(intervalTimer);
        intervalTimer = null;
        waktuHabis();
    }
};

const startTimer = () => {
    if (intervalTimer) clearInterval(intervalTimer);
    if (timerDeadlineMs === null) {
        syncTimerFromServer(sisaWaktuDetik.value, { force: true });
    }
    tickTimer();
    intervalTimer = setInterval(tickTimer, 1000);
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

const pendingPayloads = () => Object.values(pendingAnswers.value)
    .filter(item => item && item.soal_hash)
    .sort((a, b) => Number(a.nomor) - Number(b.nomor));

const syncPendingAnswers = async ({ silent = true } = {}) => {
    if (syncPromise) return syncPromise;

    const payloads = pendingPayloads();
    if (payloads.length === 0) return true;
    if (!navigator.onLine) return false;

    isSyncing.value = true;
    syncPromise = (async () => {
        try {
            const response = await axios.post(`/ujian/sesi/${sessionHash.value}/sync`, {
                answers: payloads.map(item => ({
                    soal_hash: item.soal_hash,
                    opsi_hash: item.opsi_hash,
                    essay: item.essay,
                    ragu: item.ragu,
                    client_updated_at: item.client_updated_at,
                })),
                device_fp: deviceFp.value,
            }, {
                headers: {
                    'Accept': 'application/json',
                    'X-Device-Fingerprint': deviceFp.value,
                }
            });

            if (response.data?.sisa_detik !== undefined) {
                syncTimerFromServer(response.data.sisa_detik);
            }

            payloads.forEach(clearPendingAnswer);
            return pendingPayloads().length === 0;
        } catch (e) {
            if (e.response?.status === 410) {
                router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
                return false;
            }
            if (e.response?.status === 423) {
                forceLoginAgain();
                return false;
            }

            markPendingFailed(payloads);
            if (!silent) {
                Swal.fire('Sinkronisasi Gagal', 'Masih ada jawaban yang belum tersimpan ke server. Periksa koneksi lalu coba lagi.', 'error');
            }

            return false;
        } finally {
            isSyncing.value = false;
            syncPromise = null;
        }
    })();

    return syncPromise;
};

const saveCurrentBeforeLeaving = async () => {
    if (!currentSoal.value) return;
    const nomor = currentSoal.value.nomor || currentNomor.value;

    if (pendingAnswers.value[nomor]) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
        simpanJawabanLokal(currentSoal.value);
    }

    if (navigator.onLine) {
        await syncPendingAnswers({ silent: true });
    }
};

const clearLocalExamStorage = () => {
    for (let i = 1; i <= totalSoal.value; i++) {
        removeLocalAnswer(i);
    }
    if (PENDING_STORAGE_KEY) {
        localStorage.removeItem(PENDING_STORAGE_KEY);
    }
    pendingAnswers.value = {};
};

const prosesSubmitKeServer = async () => {
    try {
        clearTimeout(debounceTimer);
        debounceTimer = null;

        if (currentSoal.value) {
            simpanJawabanLokal(currentSoal.value);
        }

        const synced = await syncPendingAnswers({ silent: false });
        if (!synced || pendingCount.value > 0) {
            isSubmitting.value = false;
            Swal.fire('Belum Tersinkron', 'Masih ada jawaban lokal yang belum terkirim. Sambungkan internet lalu coba selesai ujian lagi.', 'warning');
            return;
        }

        await axios.post(`/ujian/sesi/${sessionHash.value}/selesai`, {}, {
            headers: {
                'Accept': 'application/json',
                'X-Device-Fingerprint': deviceFp.value,
            }
        });
        clearLocalExamStorage();
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
        if (e.response?.status === 423) {
            forceLoginAgain();
            return;
        }
        Swal.fire('Error', 'Gagal memproses ujian. Cek koneksi Anda.', 'error');
    }
};

const simpanJawabanKeServer = async (soal, pendingPayload = null) => {
    const payload = pendingPayload || simpanJawabanLokal(soal);
    if (!payload) return;

    const nomorSoal = payload.nomor || currentNomor.value;
    try {
        const res = await axios.post(`/ujian/sesi/${sessionHash.value}/simpan`, {
            soal_hash: payload.soal_hash,
            opsi_hash: payload.opsi_hash,
            essay: payload.essay,
            ragu: payload.ragu,
            client_updated_at: payload.client_updated_at,
            device_fp: deviceFp.value
        }, { 
            headers: { 
                'Accept': 'application/json',
                'X-Device-Fingerprint': deviceFp.value
            } 
        });

        // Sync sisa waktu dari respons server
        if (res.data?.sisa_detik !== undefined) {
            syncTimerFromServer(res.data.sisa_detik);
        }

        // Update status terjawab di navigasi lokal
        updateNavigationState(res.data?.nomor || nomorSoal, payload.jawaban_siswa, res.data?.ragu ?? payload.ragu);
        clearPendingAnswer(payload);

    } catch (e) {
        if (e.response?.status === 410) {
            // Server bilang waktu habis ??? redirect ke hasil
            router.push(`/vue/ujian/selesai?session=${sessionHash.value}`);
        } else if (e.response?.status === 423) {
            forceLoginAgain();
        } else {
            markPendingFailed([payload]);
            console.error('Gagal sync ke server', e);
        }
    }
};

const nextSoal = async () => {
    if (currentNomor.value < totalSoal.value) {
        await saveCurrentBeforeLeaving();
        await fetchSoal(currentNomor.value + 1);
    }
};
const prevSoal = async () => {
    if (currentNomor.value > 1) {
        await saveCurrentBeforeLeaving();
        await fetchSoal(currentNomor.value - 1);
    }
};
const goToSoal = async (nomor) => {
    if (Number(nomor) !== Number(currentNomor.value)) {
        await saveCurrentBeforeLeaving();
    }
    await fetchSoal(nomor);
};

const simpanJawaban = (soal) => {
    clearTimeout(debounceTimer);
    isSaving.value = true;
    const payload = simpanJawabanLokal(soal);
    
    debounceTimer = setTimeout(() => { 
        simpanJawabanKeServer({ ...soal }, payload).then(() => {
            isSaving.value = false; 
        });
    }, 1000); 
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(e => console.log(e));
    else if (document.exitFullscreen) document.exitFullscreen();
};

const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine;
    if (isOnline.value) {
        syncPendingAnswers({ silent: true });
    }
};

const handleBeforeUnload = () => {
    if (!currentSoal.value) return;
    const nomor = currentSoal.value.nomor || currentNomor.value;
    if (pendingAnswers.value[nomor]) {
        simpanJawabanLokal(currentSoal.value);
    }
};

onMounted(() => {
    const fpData = generateDeviceFingerprint();
    deviceFp.value = fpData.hash;
    sessionHash.value = route.query.session || '';
    if (!sessionHash.value) {
        router.push('/vue/dashboard/siswa');
        return;
    }
    STORAGE_KEY = `exam_answer_${sessionHash.value}`;
    PENDING_STORAGE_KEY = `${STORAGE_KEY}_pending`;
    loadPendingAnswers();
    
    fetchExamData();
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    if (intervalTimer) clearInterval(intervalTimer);
    timerDeadlineMs = null;
    clearTimeout(debounceTimer);
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
    window.removeEventListener('beforeunload', handleBeforeUnload);
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

@media (max-width: 576px) {
    .btn-navigation {
        padding: 0.4rem 0.8rem !important;
        font-size: 0.85rem !important;
    }
    .form-check.form-switch {
        font-size: 1rem !important;
    }
    .grid-container {
        grid-template-columns: repeat(8, 1fr) !important;
        gap: 6px !important;
    }
    .grid-btn {
        border-radius: 6px !important;
        font-size: 0.85rem !important;
    }
    .grid-card {
        padding: 1rem !important;
    }
    .modal-body {
        padding: 1.25rem !important;
    }
    .modal-body p {
        font-size: 0.9rem;
        margin-bottom: 1rem !important;
    }
    .modal-body h3 {
        font-size: 1.5rem !important;
    }
    .modal-body small {
        font-size: 0.75rem !important;
    }
    .modal-footer {
        padding: 0.75rem 1.25rem !important;
    }
    .modal-footer .btn {
        padding: 0.4rem 0.8rem !important;
        font-size: 0.85rem !important;
    }
}
</style>
