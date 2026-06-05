<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-users-gear me-2 text-primary"></i>Manajemen Staf & Guru
                    </h5>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-blue"><i class="fa-solid fa-user-tie"></i></div>
                            <div>
                                <div class="stat-label">Total Staf</div>
                                <div class="stat-value">{{ staffList.length }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-green"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <div>
                                <div class="stat-label">Guru</div>
                                <div class="stat-value">{{ staffList.filter(s => (s.role || '').toLowerCase() === 'guru').length }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-purple"><i class="fa-solid fa-user-shield"></i></div>
                            <div>
                                <div class="stat-label">Admin</div>
                                <div class="stat-value">{{ staffList.filter(s => ['superadmin', 'admin'].includes((s.role || '').toLowerCase())).length }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon icon-orange"><i class="fa-solid fa-eye"></i></div>
                            <div>
                                <div class="stat-label">Pengawas</div>
                                <div class="stat-value">{{ staffList.filter(s => (s.role || '').toLowerCase() === 'pengawas').length }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <h6 class="fw-bold mb-0">Daftar Staf & Guru</h6>
                            <small class="text-muted">Kelola akun staf, guru, dan pengawas CBT</small>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="input-group" style="width: 280px;">
                                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Cari nama / NIP..." v-model="searchQuery">
                            </div>
                            <select class="form-select" style="width: 150px;" v-model="filterRole">
                                <option value="">Semua Role</option>
                                <option value="SuperAdmin">Super Admin</option>
                                <option value="Admin">Admin</option>
                                <option value="Guru">Guru</option>
                                <option value="Pengawas">Pengawas</option>
                            </select>
                            <button class="btn btn-primary" @click="openAddModal">
                                <i class="fa-solid fa-plus me-1"></i> Tambah Staf
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">NIP / Username</th>
                                    <th width="25%">Nama Lengkap</th>
                                    <th width="12%">Role</th>
                                    <th width="15%">Mapel Diampu</th>
                                    <th width="10%">Status</th>
                                    <th width="18%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(staf, idx) in paginatedStaff" :key="staf.id">
                                    <td>{{ (currentPage - 1) * perPage + idx + 1 }}</td>
                                    <td><code>{{ staf.nip }}</code></td>
                                    <td class="fw-semibold">{{ staf.nama }}</td>
                                    <td>
                                        <span class="badge rounded-pill" :class="roleBadge(staf.role)">{{ roleLabel(staf.role) }}</span>
                                    </td>
                                    <td>{{ staf.mapel || '-' }}</td>
                                    <td>
                                        <span class="badge rounded-pill" :class="staf.status === 'aktif' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                            {{ staf.status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning me-1" title="Reset Password" @click="resetPassword(staf)">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(staf)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(staf)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredStaff.length === 0">
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i> Tidak ada data staf ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-card-footer" v-if="filteredStaff.length > 0">
                        <div class="text-muted small">
                            Menampilkan {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, filteredStaff.length) }} dari {{ filteredStaff.length }} data
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item" :class="{ disabled: currentPage === 1 }"><a class="page-link" href="#" @click.prevent="currentPage--">&laquo;</a></li>
                                <li class="page-item" v-for="p in totalPages" :key="p" :class="{ active: currentPage === p }"><a class="page-link" href="#" @click.prevent="currentPage = p">{{ p }}</a></li>
                                <li class="page-item" :class="{ disabled: currentPage === totalPages }"><a class="page-link" href="#" @click.prevent="currentPage++">&raquo;</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div class="modal-backdrop-custom" v-if="showModal" @click.self="showModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">{{ isEditing ? 'Edit Staf' : 'Tambah Staf Baru' }}</h5>
                    <button class="btn-close" @click="showModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">NIP / Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.nip" placeholder="Masukkan NIP atau Username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.nama" placeholder="Masukkan nama lengkap">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="form.role">
                                <option value="SuperAdmin">Super Admin</option>
                                <option value="Admin">Admin</option>
                                <option value="Guru">Guru</option>
                                <option value="Pengawas">Pengawas</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mapel Diampu</label>
                            <select class="form-select" v-model="form.mata_pelajaran_id" :disabled="form.role !== 'Guru'">
                                <option value="">Pilih mapel</option>
                                <option v-for="mapel in mapelList" :key="mapel.id" :value="mapel.id">
                                    {{ mapel.kode_mapel }} - {{ mapel.nama_mapel }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3" v-if="!isEditing">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" v-model="form.password" placeholder="Minimal 6 karakter">
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
                    <button class="btn btn-primary" @click="saveStaff">
                        <i class="fa-solid fa-check me-1"></i> {{ isEditing ? 'Simpan Perubahan' : 'Tambah Staf' }}
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
const filterRole = ref('');
const currentPage = ref(1);
const perPage = 10;
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({ nip: '', nama: '', role: 'Guru', mata_pelajaran_id: '', password: '', status: 'aktif' });
const staffList = ref([]);
const mapelList = ref([]);

const roleBadge = (role) => {
    const r = (role || '').toLowerCase();
    if (r === 'superadmin' || r === 'admin') return 'bg-primary-subtle text-primary';
    if (r === 'guru') return 'bg-info-subtle text-info';
    if (r === 'pengawas') return 'bg-warning-subtle text-warning';
    return 'bg-secondary';
};

const roleLabel = (role) => {
    const r = (role || '').toLowerCase();
    if (r === 'superadmin') return 'Super Admin';
    if (r === 'admin') return 'Admin';
    if (r === 'guru') return 'Guru';
    if (r === 'pengawas') return 'Pengawas';
    return role;
};

const filteredStaff = computed(() => {
    return staffList.value.filter(s => {
        const matchSearch = !searchQuery.value || s.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) || s.nip.includes(searchQuery.value);
        const matchRole = !filterRole.value || (s.role || '').toLowerCase() === filterRole.value.toLowerCase();
        return matchSearch && matchRole;
    });
});

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / perPage) || 1);
const paginatedStaff = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredStaff.value.slice(start, start + perPage);
});

const loadStaff = async () => {
    try {
        const response = await axios.get('/api/management/staff', { headers: { Accept: 'application/json' } });
        const payload = response.data.data;
        staffList.value = Array.isArray(payload) ? payload : (payload.staff || []);
        mapelList.value = Array.isArray(payload) ? [] : (payload.mapels || []);
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const errorMessage = (error) => {
    const errors = error.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    if (error.response?.status === 419) return 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    if (error.response?.status === 401) return 'Sesi login berakhir. Silakan login kembali.';
    if (error.response?.status === 403) return 'Akun ini tidak memiliki izin mengelola staf.';
    return error.response?.data?.message || `Permintaan gagal diproses${error.response?.status ? ` (HTTP ${error.response.status})` : ''}.`;
};

const notifySuccess = (title, text = '') => Swal.fire({ title, text, icon: 'success', timer: 1400, showConfirmButton: false });
const notifyError = (text, title = 'Gagal') => Swal.fire({ title, text, icon: 'error' });
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

const openAddModal = () => { 
    isEditing.value = false; 
    editingId.value = null; 
    form.value = { nip: '', nama: '', role: 'Guru', mata_pelajaran_id: '', password: '', status: 'aktif' }; 
    showModal.value = true; 
};

const openEditModal = (staf) => { 
    isEditing.value = true; 
    editingId.value = staf.id; 
    let normalizedRole = staf.role;
    if (normalizedRole === 'superadmin') normalizedRole = 'SuperAdmin';
    if (normalizedRole === 'admin') normalizedRole = 'Admin';
    if (normalizedRole === 'guru') normalizedRole = 'Guru';
    if (normalizedRole === 'pengawas') normalizedRole = 'Pengawas';

    form.value = { ...staf, role: normalizedRole, mata_pelajaran_id: staf.mata_pelajaran_id || '', password: '' }; 
    showModal.value = true; 
};

const saveStaff = async () => {
    if (!form.value.nip || !form.value.nama || !form.value.role) return notifyError('NIP, nama, dan role wajib diisi.', 'Data belum lengkap');
    if (!isEditing.value && !form.value.password) return notifyError('Password wajib diisi.', 'Data belum lengkap');
    if (form.value.role !== 'Guru') form.value.mata_pelajaran_id = '';

    try {
        if (isEditing.value) {
            await axios.put(`/api/management/staff/${editingId.value}`, form.value);
            notifySuccess('Berhasil', 'Data staf diperbarui.');
        } else {
            await axios.post('/api/management/staff', form.value);
            notifySuccess('Berhasil', 'Staf baru ditambahkan.');
        }
        showModal.value = false;
        await loadStaff();
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const confirmDelete = async (staf) => { 
    if (!(await confirmAction({ title: 'Hapus staf?', text: `Data staf "${staf.nama}" akan dihapus.`, confirmButtonText: 'Ya, hapus' }))) return;
    try {
        await axios.delete(`/api/management/staff/${staf.id}`);
        await loadStaff();
        notifySuccess('Berhasil', 'Staf dihapus.');
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const resetPassword = async (staf) => { 
    const { value: formValues } = await Swal.fire({
        title: `Ubah password ${staf.nama}`,
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
        await axios.patch(`/api/management/staff/${staf.id}/password`, {
            password: formValues.password,
            password_confirmation: formValues.passwordConfirmation,
        });
        notifySuccess('Berhasil', 'Password staf diperbarui.');
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const logout = async () => { if (await confirmAction({ title: 'Keluar?', text: 'Anda akan keluar dari sistem.', confirmButtonText: 'Keluar' })) window.location.href = '/logout'; };

onMounted(() => loadStaff());
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }
.stat-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); padding: 1.25rem; display: flex; align-items: center; }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-right: 1rem; }
.icon-blue { background: #eff6ff; color: #3b82f6; } .icon-green { background: #f0fdf4; color: #22c55e; } .icon-purple { background: #faf5ff; color: #a855f7; } .icon-orange { background: #fff7ed; color: #f97316; }
.stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
.stat-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
.table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
.table-card-header { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 1rem; }
.table-card-footer { padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; }
.table-custom thead th { background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding: 0.85rem 1rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; }
.table-custom tbody td { padding: 0.85rem 1rem; vertical-align: middle; color: #334155; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.table-custom tbody tr:hover { background-color: #f8fafc; }
.bg-success-subtle { background-color: #dcfce7 !important; } .bg-danger-subtle { background-color: #fee2e2 !important; }
.bg-primary-subtle { background-color: #dbeafe !important; } .bg-info-subtle { background-color: #e0f2fe !important; } .bg-warning-subtle { background-color: #fef3c7 !important; }
.modal-backdrop-custom { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-custom { background: #fff; border-radius: 16px; width: 95%; max-width: 560px; box-shadow: 0 25px 60px rgba(0,0,0,0.15); animation: modalIn 0.25s ease; }
.modal-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body-custom { padding: 1.5rem; }
.modal-footer-custom { padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 0.75rem; }
@keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
.page-link { border: none; color: #475569; padding: 0.4rem 0.75rem; border-radius: 6px; margin: 0 2px; }
.page-item.active .page-link { background-color: #1e3a8a; border-color: #1e3a8a; }
</style>
