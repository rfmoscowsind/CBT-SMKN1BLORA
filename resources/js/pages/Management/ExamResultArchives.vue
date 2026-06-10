<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-box-archive me-2 text-primary"></i>Arsip Hasil Ujian
                </h5>
                <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                    <i class="fa-solid fa-power-off me-1"></i> Keluar
                </button>
            </div>

            <div class="container-fluid p-4">
                <div class="filter-card mb-4">
                    <div class="mb-4">
                        <h6 class="fw-bold mb-1">Pilih Arsip Kelas</h6>
                        <small class="text-muted">Pilih tahun terlebih dahulu, lalu tingkat, jurusan, dan rombel.</small>
                    </div>
                    <form class="row g-3 align-items-end" @submit.prevent="loadScheduleOptions">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Tahun</label>
                            <select class="form-select" v-model="filters.tahun" @change="resetAfterYear">
                                <option value="">Pilih tahun</option>
                                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Tingkat</label>
                            <select class="form-select" v-model="filters.tingkat" :disabled="!filters.tahun" @change="resetAfterLevel">
                                <option value="">Pilih tingkat</option>
                                <option v-for="tingkat in levels" :key="tingkat" :value="tingkat">Kelas {{ tingkat }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Jurusan</label>
                            <select class="form-select" v-model="filters.jurusan_id" :disabled="!filters.tingkat" @change="resetAfterMajor">
                                <option value="">Pilih jurusan</option>
                                <option v-for="jurusan in majors" :key="jurusan.id" :value="jurusan.id">
                                    {{ jurusan.kode }} - {{ jurusan.nama }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Rombel</label>
                            <select class="form-select" v-model="filters.rombel_id" :disabled="!filters.jurusan_id" @change="resetResults">
                                <option value="">Pilih rombel</option>
                                <option v-for="rombel in groups" :key="rombel.id" :value="rombel.id">Rombel {{ rombel.nama }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary" :disabled="!canSubmit || loadingOptions">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> {{ loadingOptions ? 'Memuat...' : 'Tampilkan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="submitted" class="filter-card mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">{{ selectedClass?.nama_kelas }} - {{ filters.tahun }}</h5>
                            <small class="text-muted">{{ selectedClass?.nama_jurusan }} - Rombel {{ selectedClass?.nama_rombel }}</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2">
                            {{ examOptions.length }} arsip ujian
                        </span>
                    </div>

                    <form class="row g-3 align-items-end" @submit.prevent="loadArchiveDetail">
                        <div class="col-md-10">
                            <label class="form-label fw-semibold">Pilih Ujian</label>
                            <select class="form-select" v-model="filters.jadwal_id" :disabled="examOptions.length === 0 || loadingResults" @change="resetResultTable">
                                <option value="">Pilih jadwal ujian</option>
                                <option v-for="schedule in examOptions" :key="schedule.id" :value="schedule.id">
                                    {{ schedule.judul }} - {{ schedule.nama_mapel }} ({{ schedule.waktu_mulai }} - {{ schedule.waktu_selesai }} WIB)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-success" :disabled="!filters.jadwal_id || loadingResults">
                                <i class="fa-solid fa-table-list me-1"></i> {{ loadingResults ? 'Memuat...' : 'Muat Arsip' }}
                            </button>
                        </div>
                    </form>

                    <div v-if="examOptions.length === 0" class="empty-state mt-3">
                        <i class="fa-regular fa-folder-open fa-2x mb-3"></i>
                        <div class="fw-semibold">Belum ada ujian pada filter ini.</div>
                    </div>
                </div>

                <div v-if="selectedSchedule && !loadingResults" class="result-card mb-4">
                    <div class="result-header">
                        <div>
                            <h6 class="fw-bold mb-1">{{ selectedSchedule.judul }}</h6>
                            <small class="text-muted">{{ selectedSchedule.nama_mapel }} | {{ selectedSchedule.waktu_mulai }} - {{ selectedSchedule.waktu_selesai }} WIB</small>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge px-3 py-2" :class="selectedSchedule.sudah_diarsipkan ? 'bg-success' : 'bg-secondary'">
                                {{ selectedSchedule.sudah_diarsipkan ? 'Sudah Arsip' : 'Belum Arsip' }}
                            </span>
                        </div>
                    </div>
                    <div class="stat-list p-3">
                        <span>Target <b>{{ selectedSchedule.statistik.total_target }}</b></span>
                        <span>Masuk <b>{{ selectedSchedule.statistik.sudah_masuk }}</b></span>
                        <span>Belum <b>{{ selectedSchedule.statistik.belum_masuk }}</b></span>
                        <span>Rata-rata <b>{{ selectedSchedule.statistik.rata_rata_nilai }}</b></span>
                        <span>Tertinggi <b>{{ score(selectedSchedule.statistik.nilai_tertinggi) }}</b></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Peserta</th>
                                    <th>NISN</th>
                                    <th>Status</th>
                                    <th>PG</th>
                                    <th>Isian</th>
                                    <th>Total</th>
                                    <th>Ranking</th>
                                    <th>Submit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in selectedSchedule.hasil" :key="row.username">
                                    <td>{{ index + 1 }}</td>
                                    <td class="fw-semibold">{{ row.name }}</td>
                                    <td>{{ row.username }}</td>
                                    <td><span class="badge" :class="statusClass(row.status)">{{ statusText(row.status) }}</span></td>
                                    <td>{{ score(row.nilai_pg) }}</td>
                                    <td>{{ score(row.nilai_isian) }}</td>
                                    <td class="fw-bold text-primary">{{ row.sesi_id ? score(row.nilai_akhir) : '-' }}</td>
                                    <td>{{ row.ranking || '-' }}</td>
                                    <td class="small">{{ row.waktu_submit || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="loadingResults" class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin fa-2x mb-3"></i>
                    <div class="fw-semibold">Memuat arsip hasil...</div>
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

const years = ref([]);
const classes = ref([]);
const selectedClass = ref(null);
const examOptions = ref([]);
const schedules = ref([]);
const submitted = ref(false);
const loadingOptions = ref(false);
const loadingResults = ref(false);
const filters = ref({ tahun: '', tingkat: '', jurusan_id: '', rombel_id: '', jadwal_id: '' });

const levels = computed(() => [...new Set(classes.value.filter(item => String(item.tahun) === String(filters.value.tahun)).map(item => item.tingkat))]);
const majors = computed(() => {
    const unique = new Map();
    classes.value
        .filter(item => String(item.tahun) === String(filters.value.tahun) && String(item.tingkat) === String(filters.value.tingkat))
        .forEach(item => unique.set(item.jurusan_id, { id: item.jurusan_id, kode: item.kode_jurusan, nama: item.nama_jurusan }));
    return [...unique.values()];
});
const groups = computed(() => classes.value
    .filter(item => String(item.tahun) === String(filters.value.tahun) && String(item.tingkat) === String(filters.value.tingkat) && String(item.jurusan_id) === String(filters.value.jurusan_id))
    .map(item => ({ id: item.rombel_id, nama: item.nama_rombel })));
const canSubmit = computed(() => filters.value.tahun && filters.value.tingkat && filters.value.jurusan_id && filters.value.rombel_id);
const selectedSchedule = computed(() => schedules.value[0] || null);

const resetResultTable = () => { schedules.value = []; };
const resetResults = () => { submitted.value = false; selectedClass.value = null; examOptions.value = []; schedules.value = []; filters.value.jadwal_id = ''; };
const resetAfterYear = () => { filters.value.tingkat = ''; filters.value.jurusan_id = ''; filters.value.rombel_id = ''; resetResults(); };
const resetAfterLevel = () => { filters.value.jurusan_id = ''; filters.value.rombel_id = ''; resetResults(); };
const resetAfterMajor = () => { filters.value.rombel_id = ''; resetResults(); };
const errorMessage = error => Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Permintaan gagal diproses.';
const notifyError = (text, title = 'Gagal') => Swal.fire({ title, text, icon: 'error' });

const loadOptions = async () => {
    const response = await axios.get('/kelola/data/arsip-hasil/options');
    years.value = response.data.data.years;
    classes.value = response.data.data.classes;
};
const classParams = () => ({
    tahun: filters.value.tahun,
    tingkat: filters.value.tingkat,
    jurusan_id: filters.value.jurusan_id,
    rombel_id: filters.value.rombel_id,
});
const loadScheduleOptions = async () => {
    if (!canSubmit.value) return;
    loadingOptions.value = true;
    try {
        const response = await axios.get('/kelola/data/arsip-hasil', { params: classParams() });
        selectedClass.value = response.data.data.kelas;
        examOptions.value = response.data.data.schedules;
        filters.value.jadwal_id = '';
        schedules.value = [];
        submitted.value = true;
    } catch (error) {
        notifyError(errorMessage(error));
    } finally {
        loadingOptions.value = false;
    }
};
const loadArchiveDetail = async () => {
    if (!filters.value.jadwal_id) return;
    loadingResults.value = true;
    try {
        const response = await axios.get('/kelola/data/arsip-hasil', { params: { ...classParams(), jadwal_id: filters.value.jadwal_id } });
        selectedClass.value = response.data.data.kelas;
        schedules.value = response.data.data.schedules;
    } catch (error) {
        notifyError(errorMessage(error));
    } finally {
        loadingResults.value = false;
    }
};
const score = value => Number(value || 0).toFixed(2);
const statusText = status => status === 'belum_masuk' ? 'Belum Masuk' : status.charAt(0).toUpperCase() + status.slice(1);
const statusClass = status => status === 'selesai' ? 'bg-success' : status === 'aktif' ? 'bg-warning text-dark' : 'bg-secondary';
const logout = () => { window.location.href = '/logout'; };

onMounted(() => loadOptions().catch(error => notifyError(errorMessage(error))));
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }
.filter-card, .result-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
.filter-card { padding: 1.5rem; }
.result-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.stat-list { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.stat-list span { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 0.35rem 0.75rem; color: #64748b; font-size: 0.78rem; }
.table-custom thead th { background: #f8fafc; color: #475569; font-weight: 600; padding: 0.85rem 1rem; font-size: 0.78rem; text-transform: uppercase; white-space: nowrap; }
.table-custom tbody td { padding: 0.85rem 1rem; vertical-align: middle; color: #334155; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; white-space: nowrap; }
.empty-state { background: #fff; border-radius: 8px; padding: 3rem; text-align: center; color: #94a3b8; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
</style>
