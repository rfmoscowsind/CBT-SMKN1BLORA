<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-calendar-days me-2 text-primary"></i>Jadwal & Master Ujian
                    </h5>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                <!-- Tab Navigation -->
                <div class="tab-nav mb-4">
                    <button class="tab-btn" :class="{ active: activeTab === 'master' }" @click="activeTab = 'master'">
                        <i class="fa-solid fa-cog me-1"></i> Master Ujian
                    </button>
                    <button class="tab-btn" :class="{ active: activeTab === 'jadwal' }" @click="activeTab = 'jadwal'">
                        <i class="fa-solid fa-calendar-check me-1"></i> Jadwal Aktif
                    </button>
                </div>

                <!-- Master Ujian Tab -->
                <div class="table-card" v-if="activeTab === 'master'">
                    <div class="table-card-header">
                        <div>
                            <h6 class="fw-bold mb-0">Master Ujian</h6>
                            <small class="text-muted">Konfigurasi ujian: paket soal, opsi acak, tampilan nilai</small>
                        </div>
                        <button class="btn btn-primary" @click="openMasterModal()">
                            <i class="fa-solid fa-plus me-1"></i> Buat Master Ujian
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="30%">Judul Ujian</th>
                                    <th width="20%">Paket Soal</th>
                                    <th width="10%">Acak Soal</th>
                                    <th width="10%">Acak Opsi</th>
                                    <th width="10%">Tampil Nilai</th>
                                    <th width="15%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(m, idx) in masterList" :key="m.id">
                                    <td>{{ idx + 1 }}</td>
                                    <td class="fw-semibold">{{ m.judul }}</td>
                                    <td>{{ m.paketSoal }}</td>
                                    <td><i :class="m.acakSoal ? 'fa-solid fa-check text-success' : 'fa-solid fa-xmark text-danger'"></i></td>
                                    <td><i :class="m.acakOpsi ? 'fa-solid fa-check text-success' : 'fa-solid fa-xmark text-danger'"></i></td>
                                    <td><i :class="m.tampilNilai ? 'fa-solid fa-check text-success' : 'fa-solid fa-xmark text-danger'"></i></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="openMasterModal(m)"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" @click="deleteMaster(m)"><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Jadwal Tab -->
                <div class="table-card" v-if="activeTab === 'jadwal'">
                    <div class="table-card-header">
                        <div>
                            <h6 class="fw-bold mb-0">Jadwal Ujian Aktif</h6>
                            <small class="text-muted">Generate token dan atur waktu pelaksanaan ujian</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button
                                class="btn btn-danger"
                                :disabled="selectedJadwalIds.length === 0"
                                @click="massDeleteJadwal"
                            >
                                <i class="fa-solid fa-trash-can me-1"></i>
                                Hapus Terpilih
                                <span v-if="selectedJadwalIds.length > 0" class="badge bg-white text-danger ms-1">{{ selectedJadwalIds.length }}</span>
                            </button>
                            <button class="btn btn-success" @click="openJadwalModal()">
                                <i class="fa-solid fa-plus me-1"></i> Buat Jadwal Baru
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="3%" class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="isAllJadwalSelected"
                                            :indeterminate.prop="isSomeJadwalSelected"
                                            @change="toggleSelectAllJadwal"
                                        >
                                    </th>
                                    <th width="4%">No</th>
                                    <th width="20%">Ujian</th>
                                    <th width="12%">Kelas</th>
                                    <th width="13%">Mulai</th>
                                    <th width="13%">Selesai</th>
                                    <th width="8%">Durasi</th>
                                    <th width="10%">Token</th>
                                    <th width="17%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(j, idx) in jadwalList" :key="j.id" :class="{ 'table-active': selectedJadwalIds.includes(j.id) }">
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :value="j.id"
                                            v-model="selectedJadwalIds"
                                        >
                                    </td>
                                    <td>{{ idx + 1 }}</td>
                                    <td class="fw-semibold">{{ j.ujian }}</td>
                                    <td>{{ j.kelas }}</td>
                                    <td class="small">{{ j.mulai }}</td>
                                    <td class="small">{{ j.selesai }}</td>
                                    <td>{{ j.durasi }} menit</td>
                                    <td><code class="bg-dark text-warning px-2 py-1 rounded">{{ j.token }}</code></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning me-1" title="Generate Token Baru" @click="regenerateToken(j)">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="openJadwalModal(j)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus jadwal" @click="deleteJadwal(j)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="jadwalList.length === 0">
                                    <td colspan="9" class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i> Belum ada jadwal ujian.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Master Ujian Modal -->
        <div class="modal-backdrop-custom" v-if="showMasterModal" @click.self="showMasterModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">{{ editingMaster ? 'Edit Master Ujian' : 'Buat Master Ujian' }}</h5>
                    <button class="btn-close" @click="showMasterModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul Ujian <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="masterForm.judul" placeholder="cth: PAS Gasal DKV 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Paket Soal <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="masterForm.paket_soal_id">
                            <option value="">Pilih Paket Soal (Ready)</option>
                            <option v-for="paket in paketList" :key="paket.id" :value="paket.id">{{ paket.nama_mapel }} - {{ paket.judul }} ({{ paket.jumlah_soal }} soal)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Visibilitas Hasil</label>
                        <select class="form-select" v-model="masterForm.hasil_visibilitas">
                            <option value="instant">Langsung tampil</option>
                            <option value="manual">Manual</option>
                            <option value="scheduled">Terjadwal</option>
                        </select>
                    </div>
                    <div class="mb-3" v-if="masterForm.hasil_visibilitas === 'scheduled'">
                        <label class="form-label fw-semibold">Tanggal Rilis Hasil</label>
                        <input type="datetime-local" class="form-control" v-model="masterForm.tanggal_rilis_hasil">
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="masterForm.acakSoal" id="sw1">
                                <label class="form-check-label fw-semibold" for="sw1">Acak Soal</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="masterForm.acakOpsi" id="sw2">
                                <label class="form-check-label fw-semibold" for="sw2">Acak Opsi</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="masterForm.tampilNilai" id="sw3">
                                <label class="form-check-label fw-semibold" for="sw3">Tampil Nilai</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showMasterModal = false">Batal</button>
                    <button class="btn btn-primary" @click="saveMaster"><i class="fa-solid fa-check me-1"></i> Simpan</button>
                </div>
            </div>
        </div>

        <!-- Jadwal Modal -->
        <div class="modal-backdrop-custom" v-if="showJadwalModal" @click.self="showJadwalModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">{{ editingJadwal ? 'Edit Jadwal' : 'Buat Jadwal Baru' }}</h5>
                    <button class="btn-close" @click="showJadwalModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Master Ujian <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="jadwalForm.master_ujian_id">
                            <option value="">Pilih Master Ujian</option>
                            <option v-for="m in masterList" :key="m.id" :value="m.id">{{ m.judul }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="jadwalForm.kelas_aktif_id">
                            <option value="">Pilih Kelas</option>
                            <option v-for="kelas in kelasList" :key="kelas.id" :value="kelas.id">{{ kelas.nama_kelas }}</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Waktu Mulai</label>
                            <input type="datetime-local" class="form-control" v-model="jadwalForm.mulai">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Waktu Selesai</label>
                            <input type="datetime-local" class="form-control" v-model="jadwalForm.selesai">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Durasi (Menit)</label>
                        <input type="number" class="form-control" v-model="jadwalForm.durasi" placeholder="cth: 120">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showJadwalModal = false">Batal</button>
                    <button class="btn btn-success" @click="saveJadwal">
                        <i class="fa-solid fa-check me-1"></i> {{ editingJadwal ? 'Simpan Perubahan' : 'Generate Jadwal & Token' }}
                    </button>
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

const activeTab = ref('master');
const showMasterModal = ref(false);
const showJadwalModal = ref(false);
const editingMaster = ref(null);
const editingJadwal = ref(null);

const masterForm = ref({ judul: '', paket_soal_id: '', acakSoal: true, acakOpsi: false, tampilNilai: false, hasil_visibilitas: 'instant', tanggal_rilis_hasil: '' });
const jadwalForm = ref({ master_ujian_id: '', kelas_aktif_id: '', mulai: '', selesai: '', durasi: 60 });
const paketList = ref([]);
const kelasList = ref([]);
const masterList = ref([]);
const jadwalList = ref([]);
const selectedJadwalIds = ref([]);

const isAllJadwalSelected = computed(() =>
    jadwalList.value.length > 0 && selectedJadwalIds.value.length === jadwalList.value.length
);
const isSomeJadwalSelected = computed(() =>
    selectedJadwalIds.value.length > 0 && selectedJadwalIds.value.length < jadwalList.value.length
);
const toggleSelectAllJadwal = () => {
    selectedJadwalIds.value = isAllJadwalSelected.value ? [] : jadwalList.value.map(j => j.id);
};
const massDeleteJadwal = async () => {
    const count = selectedJadwalIds.value.length;
    if (!(await confirmAction({
        title: `Hapus ${count} jadwal?`,
        text: 'Semua jadwal terpilih beserta data sesi dan jawaban terkait akan dihapus permanen.',
        confirmButtonText: 'Ya, hapus semua',
        danger: true,
    }))) return;
    try {
        await axios.post('/kelola/data/jadwal-ujian/mass-delete', { ids: selectedJadwalIds.value });
        selectedJadwalIds.value = [];
        await loadData();
        notifySuccess('Berhasil', `${count} jadwal dihapus.`);
    } catch (error) { notifyError(errorMessage(error)); }
};

const errorMessage = error => Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Permintaan gagal diproses.';
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
const loadData = async () => {
    const response = await axios.get('/kelola/data/jadwal-ujian');
    paketList.value = response.data.data.packages;
    kelasList.value = response.data.data.classes;
    masterList.value = response.data.data.masters.map(item => ({
        ...item,
        paketSoal: `${item.paket_soal} (${item.jumlah_soal} soal)`,
        acakSoal: item.acak_soal,
        acakOpsi: item.acak_opsi,
        tampilNilai: item.tampilkan_nilai_akhir,
    }));
    jadwalList.value = response.data.data.schedules.map(item => ({
        ...item,
        mulai: item.waktu_mulai,
        selesai: item.waktu_selesai,
        durasi: item.durasi_menit,
        bisaDiarsipkan: item.bisa_diarsipkan,
    }));
};

const openMasterModal = (m = null) => {
    editingMaster.value = m;
    masterForm.value = m ? { ...m, tanggal_rilis_hasil: m.tanggal_rilis_hasil ? m.tanggal_rilis_hasil.replace(' ', 'T').slice(0, 16) : '' } : { judul: '', paket_soal_id: '', acakSoal: true, acakOpsi: false, tampilNilai: false, hasil_visibilitas: 'instant', tanggal_rilis_hasil: '' };
    showMasterModal.value = true;
};

const saveMaster = async () => {
    try {
        const payload = {
            judul: masterForm.value.judul,
            paket_soal_id: masterForm.value.paket_soal_id,
            acak_soal: masterForm.value.acakSoal,
            acak_opsi: masterForm.value.acakOpsi,
            tampilkan_nilai_akhir: masterForm.value.tampilNilai,
            hasil_visibilitas: masterForm.value.hasil_visibilitas,
            tanggal_rilis_hasil: masterForm.value.hasil_visibilitas === 'scheduled' ? masterForm.value.tanggal_rilis_hasil : null,
        };
        if (editingMaster.value) {
            await axios.put(`/kelola/data/master-ujian/${editingMaster.value.id}`, payload);
            notifySuccess('Berhasil', 'Master ujian diperbarui.');
        } else {
            await axios.post('/kelola/data/master-ujian', payload);
            notifySuccess('Berhasil', 'Master ujian dibuat.');
        }
        showMasterModal.value = false; await loadData();
    } catch (error) { notifyError(errorMessage(error)); }
};

const deleteMaster = () => notifyError('Master yang sudah tersimpan tidak dihapus dari halaman jadwal.', 'Tidak bisa dihapus');

const openJadwalModal = (j = null) => {
    editingJadwal.value = j;
    jadwalForm.value = j ? {
        master_ujian_id: j.master_ujian_id,
        kelas_aktif_id: j.kelas_aktif_id,
        mulai: j.mulai.replace(' ', 'T').slice(0, 16),
        selesai: j.selesai.replace(' ', 'T').slice(0, 16),
        durasi: j.durasi,
    } : { master_ujian_id: '', kelas_aktif_id: '', mulai: '', selesai: '', durasi: 60 };
    showJadwalModal.value = true;
};

const saveJadwal = async () => {
    try {
        const payload = {
            master_ujian_id: jadwalForm.value.master_ujian_id,
            kelas_aktif_id: jadwalForm.value.kelas_aktif_id,
            waktu_mulai: jadwalForm.value.mulai,
            waktu_selesai: jadwalForm.value.selesai,
            durasi_menit: jadwalForm.value.durasi,
        };
        if (editingJadwal.value) {
            await axios.put(`/kelola/data/jadwal-ujian/${editingJadwal.value.id}`, payload);
            notifySuccess('Berhasil', 'Jadwal ujian diperbarui.');
        } else {
            await axios.post('/kelola/data/jadwal-ujian', payload);
            notifySuccess('Berhasil', 'Jadwal ujian dibuat.');
        }
        showJadwalModal.value = false; await loadData();
    } catch (error) { notifyError(errorMessage(error)); }
};

const regenerateToken = async j => {
    if (!(await confirmAction({ title: 'Generate token baru?', text: 'Token lama tidak bisa dipakai lagi.', confirmButtonText: 'Generate' }))) return;
    try { await axios.post(`/kelola/data/jadwal-ujian/${j.id}/token`); await loadData(); notifySuccess('Berhasil', 'Token baru dibuat.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const deleteJadwal = async j => {
    if (!(await confirmAction({ title: 'Hapus jadwal?', text: 'Jadwal dan data hasil/sesi terkait akan dihapus. User, mapel, paket soal, bank soal, dan master ujian tetap disimpan.', confirmButtonText: 'Ya, hapus', danger: true }))) return;
    try { await axios.delete(`/kelola/data/jadwal-ujian/${j.id}`); await loadData(); notifySuccess('Berhasil', 'Jadwal dihapus.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const logout = async () => { if (await confirmAction({ title: 'Keluar?', text: 'Anda akan keluar dari sistem.', confirmButtonText: 'Keluar' })) window.location.href = '/logout'; };
onMounted(() => loadData().catch(error => notifyError(errorMessage(error))));
</script>

<style scoped>
#wrapper { display: flex; width: 100vw; min-height: 100vh; }
.main-content { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; height: 100vh; overflow-y: auto; }
.top-navbar { background: #fff; height: 70px; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 1020; }

.tab-nav { display: flex; gap: 0.5rem; background: #fff; padding: 0.5rem; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
.tab-btn { border: none; background: transparent; padding: 0.7rem 1.25rem; border-radius: 8px; font-weight: 600; color: #64748b; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; }
.tab-btn:hover { background: #f1f5f9; color: #334155; }
.tab-btn.active { background: #1e3a8a; color: #fff; }

.table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); overflow: hidden; }
.table-card-header { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 1rem; }
.table-custom thead th { background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding: 0.85rem 1rem; font-size: 0.85rem; text-transform: uppercase; }
.table-custom tbody td { padding: 0.85rem 1rem; vertical-align: middle; color: #334155; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.table-custom tbody tr:hover { background-color: #f8fafc; }

.modal-backdrop-custom { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-custom { background: #fff; border-radius: 16px; width: 95%; max-width: 560px; box-shadow: 0 25px 60px rgba(0,0,0,0.15); animation: modalIn 0.25s ease; }
.modal-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body-custom { padding: 1.5rem; } .modal-footer-custom { padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 0.75rem; }
@keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
</style>
