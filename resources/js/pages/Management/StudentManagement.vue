<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-users-viewfinder me-2 text-primary"></i>Manajemen Siswa
                    </h5>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                <!-- Filter Card -->
                <div class="filter-card mb-4">
                    <div class="mb-3">
                        <h6 class="fw-bold mb-1">Pilih Kelas Peserta</h6>
                        <small class="text-muted">Pilih tingkat, jurusan, lalu rombel untuk menampilkan data siswa.</small>
                    </div>
                    <form class="row g-3 align-items-end" @submit.prevent="loadStudents">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tingkat</label>
                            <select class="form-select" v-model="filters.tingkat" @change="resetAfterLevel">
                                <option value="">Pilih tingkat</option>
                                <option v-for="tingkat in levels" :key="tingkat" :value="tingkat">{{ tingkat }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                            <select class="form-select" v-model="filters.rombel_id" :disabled="!filters.jurusan_id">
                                <option value="">Pilih rombel</option>
                                <option v-for="rombel in groups" :key="rombel.id" :value="rombel.id">{{ rombel.nama }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary" :disabled="!canSubmit || loading">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> {{ loading ? 'Memuat...' : 'Tampilkan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="isSubmitted">
                    <!-- Stats Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                                <div>
                                    <div class="stat-label">Total Siswa</div>
                                    <div class="stat-value">{{ students.filter(s => String(s.kelas_aktif_id) === String(activeClass?.id)).length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon icon-green"><i class="fa-solid fa-user-check"></i></div>
                                <div>
                                    <div class="stat-label">Aktif</div>
                                    <div class="stat-value">{{ students.filter(s => String(s.kelas_aktif_id) === String(activeClass?.id) && s.status === 'aktif').length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon icon-purple"><i class="fa-solid fa-user-xmark"></i></div>
                                <div>
                                    <div class="stat-label">Nonaktif</div>
                                    <div class="stat-value">{{ students.filter(s => String(s.kelas_aktif_id) === String(activeClass?.id) && s.status === 'nonaktif').length }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon icon-orange"><i class="fa-solid fa-school"></i></div>
                                <div>
                                    <div class="stat-label">Kelas Aktif</div>
                                    <div class="stat-value" style="font-size: 1.25rem;">{{ activeClass?.nama_kelas }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="table-card">
                        <div class="table-card-header">
                            <div>
                                <h6 class="fw-bold mb-0">Daftar Siswa - {{ activeClass?.nama_kelas }}</h6>
                                <small class="text-muted">{{ activeClass?.nama_jurusan }} (Rombel {{ activeClass?.nama_rombel }})</small>
                            </div>
                            <div class="d-flex gap-2">
                                <div class="input-group" style="width: 280px;">
                                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0" placeholder="Cari nama / NISN..." v-model="searchQuery">
                                </div>
                                <button class="btn btn-primary" @click="openAddModal">
                                    <i class="fa-solid fa-plus me-1"></i> Tambah Siswa
                                </button>
                                <button class="btn btn-success" @click="showImportModal = true">
                                    <i class="fa-solid fa-file-import me-1"></i> Import Excel
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="15%">NISN</th>
                                        <th width="25%">Nama Lengkap</th>
                                        <th width="20%">Email</th>
                                        <th width="15%">Kelas</th>
                                        <th width="10%">Status</th>
                                        <th width="15%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(siswa, idx) in paginatedStudents" :key="siswa.id">
                                        <td>{{ (currentPage - 1) * perPage + idx + 1 }}</td>
                                        <td><code>{{ siswa.nisn }}</code></td>
                                        <td class="fw-semibold">{{ siswa.nama }}</td>
                                        <td>{{ siswa.email }}</td>
                                        <td>{{ siswa.kelas }}</td>
                                        <td>
                                            <span class="badge rounded-pill" :class="siswa.status === 'aktif' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                                {{ siswa.status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(siswa)">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning me-1" title="Ubah password" @click="resetPassword(siswa)">
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(siswa)">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredStudents.length === 0">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                            Tidak ada data siswa ditemukan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="table-card-footer" v-if="filteredStudents.length > 0">
                            <div class="text-muted small">
                                Menampilkan {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, filteredStudents.length) }} dari {{ filteredStudents.length }} data
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                        <a class="page-link" href="#" @click.prevent="currentPage--">&laquo;</a>
                                    </li>
                                    <li class="page-item" v-for="p in totalPages" :key="p" :class="{ active: currentPage === p }">
                                        <a class="page-link" href="#" @click.prevent="currentPage = p">{{ p }}</a>
                                    </li>
                                    <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                        <a class="page-link" href="#" @click.prevent="currentPage++">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div class="modal-backdrop-custom" v-if="showModal" @click.self="showModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">{{ isEditing ? 'Edit Siswa' : 'Tambah Siswa Baru' }}</h5>
                    <button class="btn-close" @click="showModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">NISN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.nisn" placeholder="Masukkan NISN">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.nama" placeholder="Masukkan nama lengkap">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email (Opsional)</label>
                        <input type="email" class="form-control" v-model="form.email" placeholder="Masukkan alamat email">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="form.kelas_aktif_id" :disabled="!!activeClass">
                                <option value="">Pilih Kelas</option>
                                <option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.nama_kelas }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jurusan</label>
                            <input class="form-control" :value="selectedClass?.nama_jurusan || '-'" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ isEditing ? 'Password Baru (opsional)' : 'Password Awal' }}
                            <span v-if="!isEditing" class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" v-model="form.password" minlength="6" placeholder="Minimal 6 karakter">
                        <small class="text-muted">{{ isEditing ? 'Kosongkan jika password tidak diubah.' : 'Password dipakai siswa untuk login CBT.' }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" v-model="form.status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showModal = false">Batal</button>
                    <button class="btn btn-primary" @click="saveStudent">
                        <i class="fa-solid fa-check me-1"></i> {{ isEditing ? 'Simpan Perubahan' : 'Tambah Siswa' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div class="modal-backdrop-custom" v-if="showImportModal" @click.self="showImportModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">Import Siswa dari Excel</h5>
                    <button class="btn-close" @click="showImportModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="alert alert-info border-0">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        Unduh template terlebih dahulu untuk menyesuaikan format data.
                    </div>
                    <a href="/kelola/template-siswa" class="btn btn-outline-primary mb-3">
                        <i class="fa-solid fa-download me-1"></i> Unduh Template Excel
                    </a>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih File Excel</label>
                        <input class="form-control" type="file" accept=".xlsx,.xls">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showImportModal = false">Batal</button>
                    <button class="btn btn-success">
                        <i class="fa-solid fa-file-import me-1"></i> Proses Import
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

const searchQuery = ref('');
const currentPage = ref(1);
const perPage = 10;
const showModal = ref(false);
const showImportModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({ nisn: '', nama: '', email: '', kelas_aktif_id: '', password: '', status: 'aktif' });
const kelasList = ref([]);
const students = ref([]);
const selectedClass = computed(() => kelasList.value.find(k => String(k.id) === String(form.value.kelas_aktif_id)));

// Filter state
const filters = ref({ tingkat: '', jurusan_id: '', rombel_id: '' });
const isSubmitted = ref(false);
const activeClass = ref(null);
const loading = ref(false);

const levels = computed(() => [...new Set(kelasList.value.map(item => item.tingkat))].sort((a, b) => a - b));
const majors = computed(() => {
    const unique = new Map();
    kelasList.value
        .filter(item => String(item.tingkat) === String(filters.value.tingkat))
        .forEach(item => unique.set(item.jurusan_id, { id: item.jurusan_id, kode: item.kode_jurusan, nama: item.nama_jurusan }));
    return [...unique.values()];
});
const groups = computed(() => kelasList.value
    .filter(item => String(item.tingkat) === String(filters.value.tingkat) && String(item.jurusan_id) === String(filters.value.jurusan_id))
    .map(item => ({ id: item.rombel_id, nama: item.nama_rombel })));
const canSubmit = computed(() => filters.value.tingkat && filters.value.jurusan_id && filters.value.rombel_id);

const resetResults = () => { isSubmitted.value = false; activeClass.value = null; };
const resetAfterLevel = () => { filters.value.jurusan_id = ''; filters.value.rombel_id = ''; resetResults(); };
const resetAfterMajor = () => { filters.value.rombel_id = ''; resetResults(); };

const filteredStudents = computed(() => {
    if (!isSubmitted.value || !activeClass.value) return [];
    return students.value.filter(s => {
        const matchSearch = !searchQuery.value || s.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) || s.nisn.includes(searchQuery.value);
        return matchSearch;
    });
});

const totalPages = computed(() => Math.ceil(filteredStudents.value.length / perPage) || 1);
const paginatedStudents = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredStudents.value.slice(start, start + perPage);
});

const openAddModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.value = { 
        nisn: '', 
        nama: '', 
        email: '', 
        kelas_aktif_id: activeClass.value ? activeClass.value.id : '', 
        password: '', 
        status: 'aktif' 
    };
    showModal.value = true;
};

const openEditModal = (siswa) => {
    isEditing.value = true;
    editingId.value = siswa.id;
    form.value = { ...siswa, password: '' };
    showModal.value = true;
};

const loadStudents = async () => {
    if (!canSubmit.value) return;
    loading.value = true;
    try {
        const response = await axios.get('/kelola/data/siswa', { 
            params: filters.value,
            headers: { Accept: 'application/json' } 
        });
        students.value = response.data.data.students;
        kelasList.value = response.data.data.classes;
        
        const matched = kelasList.value.find(k =>
            String(k.tingkat) === String(filters.value.tingkat) &&
            String(k.jurusan_id) === String(filters.value.jurusan_id) &&
            String(k.rombel_id) === String(filters.value.rombel_id)
        );
        activeClass.value = matched || null;
        isSubmitted.value = true;
        currentPage.value = 1;
    } catch (error) {
        notifyError(errorMessage(error));
    } finally {
        loading.value = false;
    }
};

const loadOptions = async () => {
    const response = await axios.get('/kelola/data/siswa', { headers: { Accept: 'application/json' } });
    const classes = response.data?.data?.classes;

    if (Array.isArray(classes) && classes.length > 0) {
        kelasList.value = classes;
        return;
    }

    const master = await axios.get('/kelola/data/master-sekolah', { headers: { Accept: 'application/json' } });
    kelasList.value = master.data?.data?.kelas || [];
};

const errorMessage = (error) => {
    const errors = error.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    if (error.response?.status === 419) return 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    if (error.response?.status === 401) return 'Sesi login berakhir. Silakan login kembali.';
    if (error.response?.status === 403) return 'Akun ini tidak memiliki izin mengelola siswa.';
    return error.response?.data?.message || `Permintaan gagal diproses${error.response?.status ? ` (HTTP ${error.response.status})` : ''}.`;
};

const notifySuccess = (title, text = '') => Swal.fire({
    title,
    text,
    icon: 'success',
    timer: 1400,
    showConfirmButton: false,
});

const notifyError = (text, title = 'Gagal') => Swal.fire({
    title,
    text,
    icon: 'error',
});

const confirmAction = async ({ title, text, confirmButtonText = 'Ya, lanjutkan' }) => {
    const result = await Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
    });

    return result.isConfirmed;
};

const saveStudent = async () => {
    if (!form.value.nisn || !form.value.nama || !form.value.kelas_aktif_id) {
        return notifyError('NISN, nama, dan kelas wajib diisi.', 'Data belum lengkap');
    }
    if (!isEditing.value && !form.value.password) {
        return notifyError('Password awal wajib diisi.', 'Data belum lengkap');
    }

    try {
        if (isEditing.value) {
            await axios.put(`/kelola/data/siswa/${editingId.value}`, form.value);
            notifySuccess('Berhasil', 'Data siswa diperbarui.');
        } else {
            await axios.post('/kelola/data/siswa', form.value);
            notifySuccess('Berhasil', 'Siswa baru ditambahkan.');
        }
        showModal.value = false;
        await loadStudents();
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const resetPassword = async (siswa) => {
    const { value: formValues } = await Swal.fire({
        title: `Ubah password ${siswa.nama}`,
        html: `
            <input id="swal-password" type="password" class="swal2-input" placeholder="Password baru">
            <input id="swal-password-confirmation" type="password" class="swal2-input" placeholder="Ulangi password baru">
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            const password = document.getElementById('swal-password').value;
            const passwordConfirmation = document.getElementById('swal-password-confirmation').value;

            if (!password || password.length < 6) {
                Swal.showValidationMessage('Password minimal 6 karakter.');
                return false;
            }
            if (password !== passwordConfirmation) {
                Swal.showValidationMessage('Konfirmasi password tidak sama.');
                return false;
            }

            return { password, passwordConfirmation };
        },
    });

    if (!formValues) return;

    try {
        await axios.patch(`/kelola/data/siswa/${siswa.id}/password`, {
            password: formValues.password,
            password_confirmation: formValues.passwordConfirmation,
        });
        notifySuccess('Berhasil', 'Password siswa diperbarui.');
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const confirmDelete = async (siswa) => {
    const confirmed = await confirmAction({
        title: 'Hapus siswa?',
        text: `Data siswa "${siswa.nama}" akan dihapus.`,
        confirmButtonText: 'Ya, hapus',
    });
    if (!confirmed) return;

    try {
        await axios.delete(`/kelola/data/siswa/${siswa.id}`);
        await loadStudents();
        notifySuccess('Berhasil', 'Siswa dihapus.');
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const logout = async () => {
    if (await confirmAction({ title: 'Keluar?', text: 'Anda akan keluar dari sistem.', confirmButtonText: 'Keluar' })) {
        window.location.href = '/logout';
    }
};

onMounted(() => loadOptions().catch(error => notifyError(errorMessage(error))));
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }

.filter-card { padding: 1.5rem; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
.stat-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); padding: 1.25rem; display: flex; align-items: center; }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-right: 1rem; }
.icon-blue { background: #eff6ff; color: #3b82f6; }
.icon-green { background: #f0fdf4; color: #22c55e; }
.icon-purple { background: #faf5ff; color: #a855f7; }
.icon-orange { background: #fff7ed; color: #f97316; }
.stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
.stat-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }

.table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
.table-card-header { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 1rem; }
.table-card-footer { padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; }
.table-custom thead th { background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding: 0.85rem 1rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; }
.table-custom tbody td { padding: 0.85rem 1rem; vertical-align: middle; color: #334155; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.table-custom tbody tr:hover { background-color: #f8fafc; }
.bg-success-subtle { background-color: #dcfce7 !important; }
.bg-danger-subtle { background-color: #fee2e2 !important; }

.modal-backdrop-custom { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-custom { background: #fff; border-radius: 16px; width: 95%; max-width: 560px; box-shadow: 0 25px 60px rgba(0,0,0,0.15); animation: modalIn 0.25s ease; }
.modal-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body-custom { padding: 1.5rem; }
.modal-footer-custom { padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 0.75rem; }
@keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

.page-link { border: none; color: #475569; padding: 0.4rem 0.75rem; border-radius: 6px; margin: 0 2px; }
.page-item.active .page-link { background-color: #1e3a8a; border-color: #1e3a8a; }
</style>
