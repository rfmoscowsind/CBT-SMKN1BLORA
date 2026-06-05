<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-file-pdf me-2 text-danger"></i>Download Hasil Ujian
                </h5>
                <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                    <i class="fa-solid fa-power-off me-1"></i> Keluar
                </button>
            </div>

            <div class="container-fluid p-4">
                <div class="filter-card mb-4">
                    <div class="mb-4">
                        <h6 class="fw-bold mb-1">Pilih Hasil yang Akan Diarsipkan</h6>
                        <small class="text-muted">Pilih kelas, jurusan, rombel, dan mata pelajaran. Preview PDF akan tampil sebelum download.</small>
                    </div>
                    <form class="row g-3 align-items-end" @submit.prevent="openPreview">
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label fw-semibold">Kelas / Tingkat</label>
                            <select class="form-select" v-model="filters.tingkat" @change="resetAfterLevel">
                                <option value="">Pilih tingkat</option>
                                <option v-for="level in levels" :key="level" :value="level">Kelas {{ level }}</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label fw-semibold">Jurusan</label>
                            <select class="form-select" v-model="filters.jurusan_id" :disabled="!filters.tingkat" @change="resetAfterMajor">
                                <option value="">Pilih jurusan</option>
                                <option v-for="major in majors" :key="major.id" :value="major.id">{{ major.kode }} - {{ major.nama }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label fw-semibold">Rombel</label>
                            <select class="form-select" v-model="filters.rombel_id" :disabled="!filters.jurusan_id" @change="resetAfterGroup">
                                <option value="">Pilih rombel</option>
                                <option v-for="group in groups" :key="group.id" :value="group.id">Rombel {{ group.nama }}</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-8">
                            <label class="form-label fw-semibold">Mapel yang Diujikan</label>
                            <select class="form-select" v-model="filters.jadwal_id" :disabled="!filters.rombel_id" @change="clearPreview">
                                <option value="">Pilih mapel / jadwal</option>
                                <option v-for="schedule in filteredSchedules" :key="schedule.id" :value="schedule.id">
                                    {{ schedule.nama_mapel }} - {{ schedule.judul }}
                                </option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 d-grid">
                            <button class="btn btn-primary" :disabled="!selectedSchedule">
                                <i class="fa-solid fa-eye me-1"></i> Preview
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="selectedSchedule" class="archive-card mb-4">
                    <div>
                        <div class="small text-muted mb-1">Jadwal terpilih</div>
                        <div class="fw-bold">{{ selectedSchedule.nama_mapel }} - {{ selectedSchedule.judul }}</div>
                        <div class="small text-muted">{{ selectedSchedule.waktu_mulai }} - {{ selectedSchedule.waktu_selesai }} WIB</div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge px-3 py-2" :class="selectedSchedule.sudah_diunduh ? 'bg-success' : 'bg-secondary'">
                            {{ selectedSchedule.sudah_diunduh ? 'PDF sudah diunduh' : 'PDF belum diunduh' }}
                        </span>
                        <span v-if="selectedSchedule.sedang_aktif" class="badge bg-warning text-dark px-3 py-2">
                            Jadwal masih aktif
                        </span>
                        <button class="btn btn-danger" :disabled="!selectedSchedule.bisa_diarsipkan" @click="archiveSchedule">
                            <i class="fa-solid fa-box-archive me-1"></i> Arsipkan Jadwal
                        </button>
                    </div>
                </div>

                <div v-if="previewUrl" class="preview-card">
                    <div class="preview-header">
                        <div>
                            <h6 class="fw-bold mb-1">Preview PDF Hasil Ujian</h6>
                            <small class="text-muted">Periksa hasil sebelum mengunduh arsip final.</small>
                        </div>
                        <button class="btn btn-success" @click="downloadPdf">
                            <i class="fa-solid fa-download me-1"></i> Download PDF
                        </button>
                    </div>
                    <iframe :src="previewUrl" title="Preview PDF hasil ujian"></iframe>
                </div>

                <div v-else class="empty-state">
                    <i class="fa-regular fa-file-pdf fa-3x mb-3"></i>
                    <div class="fw-semibold">Pilih filter lengkap lalu klik Preview.</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

const classes = ref([]);
const schedules = ref([]);
const previewUrl = ref('');
const filters = ref({ tingkat: '', jurusan_id: '', rombel_id: '', jadwal_id: '' });

const levels = computed(() => [...new Set(classes.value.map(item => item.tingkat))]);
const majors = computed(() => {
    const unique = new Map();
    classes.value.filter(item => String(item.tingkat) === String(filters.value.tingkat))
        .forEach(item => unique.set(item.jurusan_id, { id: item.jurusan_id, kode: item.kode_jurusan, nama: item.nama_jurusan }));
    return [...unique.values()];
});
const groups = computed(() => classes.value
    .filter(item => String(item.tingkat) === String(filters.value.tingkat) && String(item.jurusan_id) === String(filters.value.jurusan_id))
    .map(item => ({ id: item.rombel_id, nama: item.nama_rombel })));
const selectedClass = computed(() => classes.value.find(item =>
    String(item.tingkat) === String(filters.value.tingkat)
    && String(item.jurusan_id) === String(filters.value.jurusan_id)
    && String(item.rombel_id) === String(filters.value.rombel_id)
));
const filteredSchedules = computed(() => schedules.value.filter(item => String(item.kelas_aktif_id) === String(selectedClass.value?.id)));
const selectedSchedule = computed(() => filteredSchedules.value.find(item => String(item.id) === String(filters.value.jadwal_id)));
const query = computed(() => new URLSearchParams({
    jadwal_id: filters.value.jadwal_id,
    kelas_aktif_id: selectedClass.value?.id || '',
}).toString());

const clearPreview = () => { previewUrl.value = ''; };
const resetAfterLevel = () => { filters.value.jurusan_id = ''; filters.value.rombel_id = ''; filters.value.jadwal_id = ''; clearPreview(); };
const resetAfterMajor = () => { filters.value.rombel_id = ''; filters.value.jadwal_id = ''; clearPreview(); };
const resetAfterGroup = () => { filters.value.jadwal_id = ''; clearPreview(); };
const errorMessage = error => error.response?.data?.message || 'Permintaan gagal diproses.';
const notifySuccess = (title, text = '') => Swal.fire({ title, text, icon: 'success', timer: 1400, showConfirmButton: false });
const notifyError = (text, title = 'Gagal') => Swal.fire({ title, text, icon: 'error' });
const confirmAction = async ({ title, text, confirmButtonText = 'Ya, lanjutkan', danger = false }) => {
    const result = await Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Batal',
        confirmButtonColor: danger ? '#dc2626' : '#2563eb',
    });

    return result.isConfirmed;
};
const loadOptions = async () => {
    const response = await axios.get('/kelola/data/download-hasil/options');
    classes.value = response.data.data.classes;
    schedules.value = response.data.data.schedules;
};
const openPreview = () => {
    if (!selectedSchedule.value) return;
    previewUrl.value = `/kelola/data/download-hasil/preview?${query.value}`;
};
const downloadPdf = () => {
    window.location.href = `/kelola/data/download-hasil/download?${query.value}`;
    notifySuccess('Download dimulai', 'Status jadwal akan diperbarui setelah file diproses.');
    setTimeout(async () => {
        await loadOptions();
        openPreview();
    }, 1200);
};
const archiveSchedule = async () => {
    if (!selectedSchedule.value?.bisa_diarsipkan) return;
    const warning = selectedSchedule.value.sedang_aktif
        ? 'Jadwal masih aktif. Jika diarsipkan, jadwal langsung hilang dari siswa. Lanjutkan?'
        : 'Arsipkan jadwal ini? Riwayat sesi dan jawaban tetap disimpan.';
    if (!(await confirmAction({ title: 'Arsipkan jadwal?', text: warning, confirmButtonText: 'Ya, arsipkan', danger: true }))) return;
    try {
        await axios.delete(`/kelola/data/jadwal-ujian/${selectedSchedule.value.id}`);
        filters.value.jadwal_id = '';
        clearPreview();
        await loadOptions();
        notifySuccess('Berhasil', 'Jadwal diarsipkan.');
    } catch (error) {
        notifyError(errorMessage(error));
    }
};
const logout = async () => { if (await confirmAction({ title: 'Keluar?', text: 'Anda akan keluar dari sistem.', confirmButtonText: 'Keluar' })) window.location.href = '/logout'; };

onMounted(() => loadOptions().catch(error => notifyError(errorMessage(error))));
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }
.filter-card, .preview-card, .archive-card, .empty-state { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
.filter-card { padding: 1.5rem; }
.archive-card, .preview-header { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
.preview-header { border-bottom: 1px solid #e2e8f0; }
.preview-card { overflow: hidden; }
.preview-card iframe { width: 100%; height: 720px; border: 0; display: block; }
.empty-state { padding: 5rem 2rem; text-align: center; color: #94a3b8; }
</style>
