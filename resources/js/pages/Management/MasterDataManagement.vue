<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-school me-2 text-primary"></i>Master Data Sekolah</h5>
                <button class="btn btn-outline-danger btn-sm px-3" @click="logout"><i class="fa-solid fa-power-off me-1"></i> Keluar</button>
            </div>

            <div class="container-fluid p-4">
                <div class="tab-nav mb-4">
                    <button class="tab-btn" :class="{ active: activeTab === 'tingkat' }" @click="activeTab = 'tingkat'"><i class="fa-solid fa-stairs me-1"></i> Tingkat</button>
                    <button class="tab-btn" :class="{ active: activeTab === 'jurusan' }" @click="activeTab = 'jurusan'"><i class="fa-solid fa-graduation-cap me-1"></i> Jurusan</button>
                    <button class="tab-btn" :class="{ active: activeTab === 'kelas' }" @click="activeTab = 'kelas'"><i class="fa-solid fa-door-open me-1"></i> Kelas</button>
                    <button class="tab-btn" :class="{ active: activeTab === 'mapel' }" @click="activeTab = 'mapel'"><i class="fa-solid fa-book me-1"></i> Mata Pelajaran</button>
                </div>

                <div class="table-card" v-if="activeTab === 'tingkat'">
                    <div class="table-card-header">
                        <div><h6 class="fw-bold mb-0">Manajemen Tingkat</h6><small class="text-muted">Contoh: 10, 11, 12, atau 13 sesuai kebutuhan sekolah.</small></div>
                        <button class="btn btn-primary" @click="openTingkatModal"><i class="fa-solid fa-plus me-1"></i> Tambah Tingkat</button>
                    </div>
                    <table class="table table-custom mb-0">
                        <thead><tr><th width="8%">No</th><th>Tingkat</th><th width="15%" class="text-center">Aksi</th></tr></thead>
                        <tbody>
                            <tr v-for="(item, index) in tingkats" :key="item.id">
                                <td>{{ index + 1 }}</td><td class="fw-semibold">Kelas {{ item.nama_tingkat }}</td>
                                <td class="text-center"><button class="btn btn-sm btn-outline-danger" @click="deleteTingkat(item)"><i class="fa-solid fa-trash-can"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-card" v-if="activeTab === 'jurusan'">
                    <div class="table-card-header">
                        <div><h6 class="fw-bold mb-0">Manajemen Jurusan</h6><small class="text-muted">Kode jurusan dipakai otomatis saat membentuk nama kelas.</small></div>
                        <button class="btn btn-primary" @click="openJurusanModal()"><i class="fa-solid fa-plus me-1"></i> Tambah Jurusan</button>
                    </div>
                    <table class="table table-custom mb-0">
                        <thead><tr><th width="8%">No</th><th width="18%">Kode</th><th>Nama Jurusan</th><th width="15%" class="text-center">Aksi</th></tr></thead>
                        <tbody>
                            <tr v-for="(item, index) in jurusans" :key="item.id">
                                <td>{{ index + 1 }}</td><td><code>{{ item.kode_jurusan }}</code></td><td class="fw-semibold">{{ item.nama_jurusan }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1" @click="openJurusanModal(item)"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" @click="deleteJurusan(item)"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="activeTab === 'kelas'">
                    <div class="table-card">
                        <div class="table-card-header">
                            <div><h6 class="fw-bold mb-0">Kelas Aktif</h6><small class="text-muted">Pilih tingkat dan jurusan. Nomor rombel ditentukan otomatis.</small></div>
                            <button class="btn btn-primary" @click="openKelasModal"><i class="fa-solid fa-plus me-1"></i> Create Kelas</button>
                        </div>
                        <table class="table table-custom mb-0">
                            <thead><tr><th width="8%">No</th><th width="15%">Tingkat</th><th>Jurusan</th><th width="15%">Rombel</th><th width="20%">Nama Kelas</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, index) in kelas" :key="item.id">
                                    <td>{{ index + 1 }}</td><td>{{ item.tingkat }}</td><td>{{ item.kode_jurusan }} - {{ item.nama_jurusan }}</td><td>{{ item.nama_rombel }}</td><td class="fw-semibold">{{ item.nama_kelas }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-card mt-4 border border-success-subtle" v-if="createdClass">
                        <div class="table-card-header bg-success-subtle">
                            <div>
                                <h6 class="fw-bold mb-1 text-success"><i class="fa-solid fa-circle-check me-1"></i> Kelas {{ createdClass.nama_kelas }} berhasil dibuat</h6>
                                <small class="text-muted">Lanjutkan dengan import siswa. Semua siswa pada file otomatis dimasukkan ke kelas ini.</small>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="row align-items-end g-3">
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold">File Excel / CSV Siswa</label>
                                    <input class="form-control" type="file" accept=".xlsx,.xls,.csv" @change="importFile = $event.target.files[0]">
                                </div>
                                <div class="col-md-5">
                                    <a href="/kelola/template-siswa" class="btn btn-outline-primary me-2"><i class="fa-solid fa-download me-1"></i> Template</a>
                                    <button class="btn btn-success" @click="importStudents"><i class="fa-solid fa-file-import me-1"></i> Import ke {{ createdClass.nama_kelas }}</button>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0" v-if="importResult">
                                Berhasil: <b>{{ importResult.imported }}</b>, gagal: <b>{{ importResult.failed }}</b>.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-card" v-if="activeTab === 'mapel'">
                    <div class="table-card-header">
                        <div>
                            <h6 class="fw-bold mb-0">Mata Pelajaran</h6>
                            <small class="text-muted">Manajemen mata pelajaran aktif dari database.</small>
                        </div>
                        <button class="btn btn-primary" @click="openMapelModal()"><i class="fa-solid fa-plus me-1"></i> Tambah Mapel</button>
                    </div>
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th width="8%">No</th>
                                <th width="20%">Kode</th>
                                <th>Nama Mata Pelajaran</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in mapels" :key="item.id">
                                <td>{{ index + 1 }}</td>
                                <td><code>{{ item.kode_mapel }}</code></td>
                                <td class="fw-semibold">{{ item.nama_mapel }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1" @click="openMapelModal(item)"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" @click="deleteMapel(item)"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                            <tr v-if="!mapels.length">
                                <td colspan="4" class="text-center text-muted py-4">Belum ada mata pelajaran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'tingkat'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom"><h5 class="fw-bold mb-0">Tambah Tingkat</h5><button class="btn-close" @click="modal = ''"></button></div>
                <div class="modal-body-custom"><label class="form-label fw-semibold">Nomor Tingkat</label><input class="form-control" type="number" min="1" max="20" v-model="tingkatForm.nama_tingkat" placeholder="Contoh: 10"></div>
                <div class="modal-footer-custom"><button class="btn btn-light" @click="modal = ''">Batal</button><button class="btn btn-primary" @click="saveTingkat">Simpan</button></div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'jurusan'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom"><h5 class="fw-bold mb-0">{{ jurusanForm.id ? 'Edit' : 'Tambah' }} Jurusan</h5><button class="btn-close" @click="modal = ''"></button></div>
                <div class="modal-body-custom">
                    <label class="form-label fw-semibold">Kode Jurusan</label><input class="form-control mb-3" v-model="jurusanForm.kode_jurusan" placeholder="Contoh: TE">
                    <label class="form-label fw-semibold">Nama Jurusan</label><input class="form-control" v-model="jurusanForm.nama_jurusan" placeholder="Contoh: Teknik Elektronika">
                </div>
                <div class="modal-footer-custom"><button class="btn btn-light" @click="modal = ''">Batal</button><button class="btn btn-primary" @click="saveJurusan">Simpan</button></div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'kelas'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom"><h5 class="fw-bold mb-0">Create Kelas Otomatis</h5><button class="btn-close" @click="modal = ''"></button></div>
                <div class="modal-body-custom">
                    <label class="form-label fw-semibold">Tingkat</label>
                    <select class="form-select" v-model="kelasForm.tingkat_id"><option value="">Pilih tingkat</option><option v-for="item in tingkats" :key="item.id" :value="item.id">{{ item.nama_tingkat }}</option></select>
                    <label class="form-label fw-semibold mt-3">Jurusan</label>
                    <select class="form-select" v-model="kelasForm.jurusan_id"><option value="">Pilih jurusan</option><option v-for="item in jurusans" :key="item.id" :value="item.id">{{ item.kode_jurusan }} - {{ item.nama_jurusan }}</option></select>
                    <div class="alert alert-info mt-3 mb-0">Contoh: jika <b>10 TE 1</b> sudah ada, create berikutnya otomatis menjadi <b>10 TE 2</b>.</div>
                </div>
                <div class="modal-footer-custom"><button class="btn btn-light" @click="modal = ''">Batal</button><button class="btn btn-primary" @click="generateClass">Create Kelas</button></div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'mapel'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom"><h5 class="fw-bold mb-0">{{ mapelForm.id ? 'Edit' : 'Tambah' }} Mata Pelajaran</h5><button class="btn-close" @click="modal = ''"></button></div>
                <div class="modal-body-custom">
                    <label class="form-label fw-semibold">Kode Mapel</label><input class="form-control mb-3" v-model="mapelForm.kode_mapel" placeholder="Contoh: DDG">
                    <label class="form-label fw-semibold">Nama Mata Pelajaran</label><input class="form-control" v-model="mapelForm.nama_mapel" placeholder="Contoh: Dasar Desain Grafis">
                </div>
                <div class="modal-footer-custom"><button class="btn btn-light" @click="modal = ''">Batal</button><button class="btn btn-primary" @click="saveMapel">Simpan</button></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

const activeTab = ref('tingkat');
const modal = ref('');
const tingkats = ref([]);
const jurusans = ref([]);
const kelas = ref([]);
const mapels = ref([]);
const tingkatForm = ref({ nama_tingkat: '' });
const jurusanForm = ref({ id: null, kode_jurusan: '', nama_jurusan: '' });
const kelasForm = ref({ tingkat_id: '', jurusan_id: '' });
const mapelForm = ref({ id: null, kode_mapel: '', nama_mapel: '' });
const createdClass = ref(null);
const importFile = ref(null);
const importResult = ref(null);

const errorMessage = error => Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Permintaan gagal diproses.';
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
const loadMaster = async () => {
    const response = await axios.get('/kelola/data/master-sekolah');
    tingkats.value = response.data.data.tingkats;
    jurusans.value = response.data.data.jurusans;
    kelas.value = response.data.data.kelas;
    mapels.value = response.data.data.mapels;
};
const openTingkatModal = () => { tingkatForm.value = { nama_tingkat: '' }; modal.value = 'tingkat'; };
const saveTingkat = async () => {
    try { await axios.post('/kelola/data/tingkat', tingkatForm.value); modal.value = ''; await loadMaster(); notifySuccess('Berhasil', 'Tingkat ditambahkan.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const deleteTingkat = async item => {
    if (!(await confirmAction({ title: 'Hapus tingkat?', text: `Tingkat ${item.nama_tingkat} akan dihapus.`, confirmButtonText: 'Ya, hapus' }))) return;
    try { await axios.delete(`/kelola/data/tingkat/${item.id}`); await loadMaster(); notifySuccess('Berhasil', 'Tingkat dihapus.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const openJurusanModal = item => { jurusanForm.value = item ? { ...item } : { id: null, kode_jurusan: '', nama_jurusan: '' }; modal.value = 'jurusan'; };
const saveJurusan = async () => {
    try {
        const editing = !!jurusanForm.value.id;
        editing ? await axios.put(`/kelola/data/jurusan/${jurusanForm.value.id}`, jurusanForm.value) : await axios.post('/kelola/data/jurusan', jurusanForm.value);
        modal.value = ''; await loadMaster();
        notifySuccess('Berhasil', editing ? 'Jurusan diperbarui.' : 'Jurusan ditambahkan.');
    } catch (error) { notifyError(errorMessage(error)); }
};
const deleteJurusan = async item => {
    if (!(await confirmAction({ title: 'Hapus jurusan?', text: `Jurusan ${item.kode_jurusan} akan dihapus.`, confirmButtonText: 'Ya, hapus' }))) return;
    try { await axios.delete(`/kelola/data/jurusan/${item.id}`); await loadMaster(); notifySuccess('Berhasil', 'Jurusan dihapus.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const openMapelModal = item => { mapelForm.value = item ? { ...item } : { id: null, kode_mapel: '', nama_mapel: '' }; modal.value = 'mapel'; };
const saveMapel = async () => {
    try {
        const editing = !!mapelForm.value.id;
        editing ? await axios.put(`/kelola/master/mapel/${mapelForm.value.id}`, mapelForm.value) : await axios.post('/kelola/mapel', mapelForm.value);
        modal.value = ''; await loadMaster();
        notifySuccess('Berhasil', editing ? 'Mata pelajaran diperbarui.' : 'Mata pelajaran ditambahkan.');
    } catch (error) { notifyError(errorMessage(error)); }
};
const deleteMapel = async item => {
    if (!(await confirmAction({ title: 'Hapus mata pelajaran?', text: `${item.nama_mapel} akan dihapus.`, confirmButtonText: 'Ya, hapus' }))) return;
    try { await axios.delete(`/kelola/master/mapel/${item.id}`); await loadMaster(); notifySuccess('Berhasil', 'Mata pelajaran dihapus.'); }
    catch (error) { notifyError(errorMessage(error)); }
};
const openKelasModal = () => { kelasForm.value = { tingkat_id: '', jurusan_id: '' }; modal.value = 'kelas'; };
const generateClass = async () => {
    try {
        const response = await axios.post('/kelola/data/kelas/generate', kelasForm.value);
        createdClass.value = response.data.data.kelas;
        importFile.value = null; importResult.value = null; modal.value = ''; activeTab.value = 'kelas';
        await loadMaster();
        notifySuccess('Berhasil', `Kelas ${createdClass.value.nama_kelas} dibuat.`);
    } catch (error) { notifyError(errorMessage(error)); }
};
const importStudents = async () => {
    if (!importFile.value) return notifyError('Pilih file siswa terlebih dahulu.', 'File belum dipilih');
    const body = new FormData(); body.append('file', importFile.value);
    try { const response = await axios.post(`/kelola/data/kelas/${createdClass.value.id}/import-siswa`, body); importResult.value = response.data.data; notifySuccess('Import selesai', `${importResult.value.imported} siswa berhasil diimport.`); }
    catch (error) { notifyError(errorMessage(error)); }
};
const logout = async () => { if (await confirmAction({ title: 'Keluar?', text: 'Anda akan keluar dari sistem.', confirmButtonText: 'Keluar' })) window.location.href = '/logout'; };
onMounted(() => loadMaster().catch(error => notifyError(errorMessage(error))));
</script>

<style scoped>
#wrapper{display:flex;width:100vw;min-height:100vh}.main-content{flex-grow:1;min-width:0;height:100vh;overflow-y:auto}.top-navbar{height:70px;padding:0 2rem;background:#fff;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.02)}.tab-nav{display:flex;gap:.5rem;background:#fff;padding:.5rem;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.04)}.tab-btn{border:0;background:transparent;padding:.7rem 1.25rem;border-radius:8px;font-weight:600;color:#64748b}.tab-btn.active{background:#1e3a8a;color:#fff}.table-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.04);overflow:hidden}.table-card-header{padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9}.table-custom th,.table-custom td{padding:.85rem 1rem;vertical-align:middle}.modal-backdrop-custom{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;display:flex;align-items:center;justify-content:center}.modal-custom{background:#fff;border-radius:16px;width:95%;max-width:540px}.modal-header-custom,.modal-footer-custom{padding:1.25rem 1.5rem;display:flex;justify-content:space-between;gap:.75rem}.modal-body-custom{padding:1.5rem}.bg-success-subtle{background:#dcfce7!important}
</style>
