<template>
    <div id="wrapper">
        <AdminSidebar />

        <main class="main-content">
            <header class="top-navbar">
                <div>
                    <p class="eyebrow mb-1">Manajemen</p>
                    <h1 class="page-title mb-0">Master Sekolah</h1>
                </div>
                <button class="btn btn-outline-danger btn-sm" @click="logout">
                    <i class="fa-solid fa-power-off"></i>
                    <span>Keluar</span>
                </button>
            </header>

            <div class="page-shell">
                <section class="summary-grid">
                    <button
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="summary-card"
                        :class="{ active: activeTab === card.key }"
                        type="button"
                        @click="activeTab = card.key"
                    >
                        <span class="summary-icon"><i :class="card.icon"></i></span>
                        <span>
                            <strong>{{ card.value }}</strong>
                            <small>{{ card.label }}</small>
                        </span>
                    </button>
                </section>

                <section class="workspace-card">
                    <div class="workspace-toolbar">
                        <div class="tab-strip" role="tablist" aria-label="Master sekolah">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                class="tab-btn"
                                :class="{ active: activeTab === tab.key }"
                                type="button"
                                @click="activeTab = tab.key"
                            >
                                <i :class="tab.icon"></i>
                                <span>{{ tab.label }}</span>
                            </button>
                        </div>

                        <button class="btn btn-primary action-btn" type="button" @click="openActiveModal">
                            <i class="fa-solid fa-plus"></i>
                            <span>{{ activeActionLabel }}</span>
                        </button>
                    </div>

                    <div class="panel-heading">
                        <div>
                            <h2>{{ activeTabMeta.label }}</h2>
                            <p>{{ activeTabMeta.caption }}</p>
                        </div>
                        <span class="data-count">{{ activeTabMeta.count }} data</span>
                    </div>

                    <div v-if="activeTab === 'tingkat'" class="responsive-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tingkat</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in tingkats" :key="item.id">
                                    <td class="row-number">{{ index + 1 }}</td>
                                    <td>
                                        <div class="primary-cell">Kelas {{ item.nama_tingkat }}</div>
                                    </td>
                                    <td class="text-end">
                                        <button class="icon-btn danger" type="button" title="Hapus" @click="deleteTingkat(item)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!tingkats.length">
                                    <td colspan="3" class="empty-state">Belum ada tingkat.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="activeTab === 'jurusan'" class="responsive-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Jurusan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in jurusans" :key="item.id">
                                    <td class="row-number">{{ index + 1 }}</td>
                                    <td><span class="code-pill">{{ item.kode_jurusan }}</span></td>
                                    <td>
                                        <div class="primary-cell">{{ item.nama_jurusan }}</div>
                                    </td>
                                    <td class="text-end">
                                        <button class="icon-btn" type="button" title="Edit" @click="openJurusanModal(item)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="icon-btn danger" type="button" title="Hapus" @click="deleteJurusan(item)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!jurusans.length">
                                    <td colspan="4" class="empty-state">Belum ada jurusan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="activeTab === 'kelas'" class="stacked-panel">
                        <div class="responsive-table">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kelas</th>
                                        <th>Tingkat</th>
                                        <th>Jurusan</th>
                                        <th>Rombel</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in kelas" :key="item.id">
                                        <td class="row-number">{{ index + 1 }}</td>
                                        <td>
                                            <div class="primary-cell">{{ item.nama_kelas }}</div>
                                        </td>
                                        <td>{{ item.tingkat }}</td>
                                        <td>{{ item.kode_jurusan }} - {{ item.nama_jurusan }}</td>
                                        <td>{{ item.nama_rombel }}</td>
                                    </tr>
                                    <tr v-if="!kelas.length">
                                        <td colspan="5" class="empty-state">Belum ada kelas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="import-panel" v-if="createdClass">
                            <div>
                                <p class="eyebrow success mb-1">Kelas baru</p>
                                <h3>{{ createdClass.nama_kelas }}</h3>
                            </div>
                            <div class="import-controls">
                                <input class="form-control" type="file" accept=".xlsx,.xls,.csv" @change="importFile = $event.target.files[0]">
                                <a href="/kelola/template-siswa" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-download"></i>
                                    <span>Template</span>
                                </a>
                                <button class="btn btn-success" type="button" @click="importStudents">
                                    <i class="fa-solid fa-file-import"></i>
                                    <span>Import</span>
                                </button>
                            </div>
                            <div class="import-result" v-if="importResult">
                                Berhasil {{ importResult.imported }}, gagal {{ importResult.failed }}.
                            </div>
                        </div>
                    </div>

                    <div v-if="activeTab === 'mapel'" class="responsive-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Mata Pelajaran</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in mapels" :key="item.id">
                                    <td class="row-number">{{ index + 1 }}</td>
                                    <td><span class="code-pill">{{ item.kode_mapel }}</span></td>
                                    <td>
                                        <div class="primary-cell">{{ item.nama_mapel }}</div>
                                    </td>
                                    <td class="text-end">
                                        <button class="icon-btn" type="button" title="Edit" @click="openMapelModal(item)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="icon-btn danger" type="button" title="Hapus" @click="deleteMapel(item)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!mapels.length">
                                    <td colspan="4" class="empty-state">Belum ada mata pelajaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>

        <div class="modal-backdrop-custom" v-if="modal === 'tingkat'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5>Tambah Tingkat</h5>
                    <button class="btn-close" type="button" @click="modal = ''"></button>
                </div>
                <div class="modal-body-custom">
                    <label class="form-label">Nomor Tingkat</label>
                    <input class="form-control" type="number" min="1" max="20" v-model="tingkatForm.nama_tingkat" placeholder="Contoh: 10">
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" type="button" @click="modal = ''">Batal</button>
                    <button class="btn btn-primary" type="button" @click="saveTingkat">Simpan</button>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'jurusan'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5>{{ jurusanForm.id ? 'Edit' : 'Tambah' }} Jurusan</h5>
                    <button class="btn-close" type="button" @click="modal = ''"></button>
                </div>
                <div class="modal-body-custom">
                    <label class="form-label">Kode Jurusan</label>
                    <input class="form-control mb-3" v-model="jurusanForm.kode_jurusan" placeholder="Contoh: TE">
                    <label class="form-label">Nama Jurusan</label>
                    <input class="form-control" v-model="jurusanForm.nama_jurusan" placeholder="Contoh: Teknik Elektronika">
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" type="button" @click="modal = ''">Batal</button>
                    <button class="btn btn-primary" type="button" @click="saveJurusan">Simpan</button>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'kelas'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5>Buat Kelas</h5>
                    <button class="btn-close" type="button" @click="modal = ''"></button>
                </div>
                <div class="modal-body-custom">
                    <label class="form-label">Tingkat</label>
                    <select class="form-select" v-model="kelasForm.tingkat_id">
                        <option value="">Pilih tingkat</option>
                        <option v-for="item in tingkats" :key="item.id" :value="item.id">{{ item.nama_tingkat }}</option>
                    </select>
                    <label class="form-label mt-3">Jurusan</label>
                    <select class="form-select" v-model="kelasForm.jurusan_id">
                        <option value="">Pilih jurusan</option>
                        <option v-for="item in jurusans" :key="item.id" :value="item.id">{{ item.kode_jurusan }} - {{ item.nama_jurusan }}</option>
                    </select>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" type="button" @click="modal = ''">Batal</button>
                    <button class="btn btn-primary" type="button" @click="generateClass">Buat</button>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="modal === 'mapel'" @click.self="modal = ''">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5>{{ mapelForm.id ? 'Edit' : 'Tambah' }} Mata Pelajaran</h5>
                    <button class="btn-close" type="button" @click="modal = ''"></button>
                </div>
                <div class="modal-body-custom">
                    <label class="form-label">Kode Mapel</label>
                    <input class="form-control mb-3" v-model="mapelForm.kode_mapel" placeholder="Contoh: DDG">
                    <label class="form-label">Nama Mata Pelajaran</label>
                    <input class="form-control" v-model="mapelForm.nama_mapel" placeholder="Contoh: Dasar Desain Grafis">
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" type="button" @click="modal = ''">Batal</button>
                    <button class="btn btn-primary" type="button" @click="saveMapel">Simpan</button>
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

const tabs = [
    { key: 'tingkat', label: 'Tingkat', icon: 'fa-solid fa-stairs', caption: 'Urutan jenjang kelas.' },
    { key: 'jurusan', label: 'Jurusan', icon: 'fa-solid fa-graduation-cap', caption: 'Kode dan nama jurusan.' },
    { key: 'kelas', label: 'Kelas', icon: 'fa-solid fa-door-open', caption: 'Daftar rombel aktif.' },
    { key: 'mapel', label: 'Mapel', icon: 'fa-solid fa-book', caption: 'Mata pelajaran aktif.' },
];

const summaryCards = computed(() => [
    { key: 'tingkat', label: 'Tingkat', value: tingkats.value.length, icon: 'fa-solid fa-stairs' },
    { key: 'jurusan', label: 'Jurusan', value: jurusans.value.length, icon: 'fa-solid fa-graduation-cap' },
    { key: 'kelas', label: 'Kelas', value: kelas.value.length, icon: 'fa-solid fa-door-open' },
    { key: 'mapel', label: 'Mapel', value: mapels.value.length, icon: 'fa-solid fa-book' },
]);

const activeTabMeta = computed(() => {
    const base = tabs.find(tab => tab.key === activeTab.value) || tabs[0];
    const countMap = {
        tingkat: tingkats.value.length,
        jurusan: jurusans.value.length,
        kelas: kelas.value.length,
        mapel: mapels.value.length,
    };

    return { ...base, count: countMap[base.key] || 0 };
});

const activeActionLabel = computed(() => ({
    tingkat: 'Tambah Tingkat',
    jurusan: 'Tambah Jurusan',
    kelas: 'Buat Kelas',
    mapel: 'Tambah Mapel',
})[activeTab.value] || 'Tambah Data');

const openActiveModal = () => {
    if (activeTab.value === 'tingkat') openTingkatModal();
    if (activeTab.value === 'jurusan') openJurusanModal();
    if (activeTab.value === 'kelas') openKelasModal();
    if (activeTab.value === 'mapel') openMapelModal();
};

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
#wrapper {
    display: flex;
    width: 100vw;
    min-height: 100vh;
    background: #f5f7fb;
}

.main-content {
    flex: 1;
    min-width: 0;
    height: 100vh;
    overflow-y: auto;
}

.top-navbar {
    min-height: 76px;
    padding: 1rem 1.5rem;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.page-title {
    color: #111827;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 0;
}

.eyebrow {
    color: #64748b;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.eyebrow.success {
    color: #15803d;
}

.page-shell {
    padding: 1.25rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}

.summary-card {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 8px;
    padding: .9rem;
    display: flex;
    align-items: center;
    gap: .8rem;
    text-align: left;
    color: #334155;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.summary-card:hover,
.summary-card.active {
    border-color: #2563eb;
    box-shadow: 0 10px 24px rgba(37, 99, 235, .12);
    transform: translateY(-1px);
}

.summary-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #1d4ed8;
    flex: 0 0 auto;
}

.summary-card strong {
    display: block;
    color: #0f172a;
    font-size: 1.3rem;
    line-height: 1;
}

.summary-card small {
    color: #64748b;
    font-weight: 700;
}

.workspace-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.workspace-toolbar {
    padding: .85rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .85rem;
}

.tab-strip {
    display: flex;
    gap: .35rem;
    padding: .25rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f8fafc;
    overflow-x: auto;
}

.tab-btn {
    border: 0;
    background: transparent;
    color: #475569;
    border-radius: 6px;
    min-height: 38px;
    padding: 0 .85rem;
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    font-weight: 800;
    white-space: nowrap;
}

.tab-btn.active {
    background: #1d4ed8;
    color: #fff;
}

.action-btn,
.top-navbar .btn,
.import-controls .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    white-space: nowrap;
}

.panel-heading {
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.panel-heading h2 {
    margin: 0;
    color: #111827;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: 0;
}

.panel-heading p {
    margin: .15rem 0 0;
    color: #64748b;
    font-size: .88rem;
}

.data-count {
    border: 1px solid #dbeafe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: .3rem .65rem;
    font-size: .8rem;
    font-weight: 800;
    white-space: nowrap;
}

.responsive-table {
    width: 100%;
    overflow-x: auto;
}

.table {
    min-width: 680px;
}

.table th {
    color: #64748b;
    background: #f8fafc;
    font-size: .76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 1px solid #e5e7eb;
    padding: .8rem 1rem;
}

.table td {
    color: #334155;
    border-color: #f1f5f9;
    padding: .8rem 1rem;
}

.row-number {
    color: #94a3b8;
    width: 72px;
    font-weight: 800;
}

.primary-cell {
    color: #0f172a;
    font-weight: 800;
}

.code-pill {
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    display: inline-flex;
    min-width: 54px;
    justify-content: center;
    padding: .22rem .55rem;
    font-size: .8rem;
    font-weight: 800;
}

.icon-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #dbeafe;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: .35rem;
}

.icon-btn.danger {
    border-color: #fee2e2;
    background: #fef2f2;
    color: #dc2626;
}

.empty-state {
    color: #94a3b8 !important;
    text-align: center;
    padding: 2rem 1rem !important;
}

.stacked-panel {
    display: grid;
    gap: 1rem;
}

.import-panel {
    margin: 0 1rem 1rem;
    padding: 1rem;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    background: #f0fdf4;
    display: grid;
    gap: .8rem;
}

.import-panel h3 {
    margin: 0;
    color: #14532d;
    font-size: 1.05rem;
    font-weight: 800;
}

.import-controls {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) auto auto;
    gap: .6rem;
}

.import-result {
    color: #166534;
    font-weight: 800;
}

.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-custom {
    background: #fff;
    border-radius: 8px;
    width: min(95vw, 520px);
    box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
}

.modal-header-custom,
.modal-footer-custom {
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}

.modal-header-custom {
    border-bottom: 1px solid #e5e7eb;
}

.modal-header-custom h5 {
    margin: 0;
    color: #111827;
    font-size: 1rem;
    font-weight: 800;
}

.modal-body-custom {
    padding: 1.25rem;
}

.modal-footer-custom {
    border-top: 1px solid #e5e7eb;
    justify-content: flex-end;
}

.form-label {
    color: #334155;
    font-weight: 800;
}

@media (max-width: 992px) {
    #wrapper {
        display: block;
    }

    .main-content {
        height: auto;
        min-height: 100vh;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .workspace-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .top-navbar,
    .panel-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .page-shell {
        padding: .85rem;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .summary-card {
        padding: .8rem;
    }

    .tab-strip {
        width: 100%;
    }

    .import-controls {
        grid-template-columns: 1fr;
    }
}
</style>
