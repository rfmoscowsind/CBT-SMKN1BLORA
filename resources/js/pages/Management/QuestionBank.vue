<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            <div class="top-navbar">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-file-circle-check me-2 text-primary"></i>Bank Soal & Paket
                </h5>
                <button class="btn btn-outline-danger btn-sm px-3" @click="logout"><i class="fa-solid fa-power-off me-1"></i> Keluar</button>
            </div>

            <div class="container-fluid p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-blue"><i class="fa-solid fa-box-archive"></i></div><div><div class="stat-label">Total Paket</div><div class="stat-value">{{ packages.length }}</div></div></div></div>
                    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-label">Siap Ujian</div><div class="stat-value">{{ packages.filter(item => item.status === 'ready').length }}</div></div></div></div>
                    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-purple"><i class="fa-solid fa-list-ol"></i></div><div><div class="stat-label">Total Soal</div><div class="stat-value">{{ packages.reduce((sum, item) => sum + Number(item.jumlah_soal), 0) }}</div></div></div></div>
                </div>

                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <h6 class="fw-bold mb-0">Daftar Paket Soal</h6>
                            <small class="text-muted">Data tersimpan di PostgreSQL. Pemilik paket mengikuti akun pembuat.</small>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <input class="form-control" style="width: 240px;" v-model="search" placeholder="Cari paket atau mapel...">
                            <select class="form-select" style="width: 140px;" v-model="statusFilter">
                                <option value="">Semua Status</option><option value="draft">Draft</option><option value="ready">Ready</option>
                            </select>
                            <button class="btn btn-primary" @click="openPackage()"><i class="fa-solid fa-plus me-1"></i> Buat Paket</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead><tr><th>No</th><th>Judul Paket</th><th>Mapel</th><th>Guru</th><th>Soal</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, index) in filteredPackages" :key="item.id">
                                    <td>{{ index + 1 }}</td><td class="fw-semibold">{{ item.judul }}</td><td>{{ item.nama_mapel }}</td><td>{{ item.guru }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ item.jumlah_soal }} soal</span></td>
                                    <td><span class="badge" :class="item.status === 'ready' ? 'bg-success' : 'bg-warning text-dark'">{{ item.status.toUpperCase() }}</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-info me-1" @click="openWizard(item)"><i class="fa-solid fa-list-check me-1"></i> Kelola</button>
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="openPackage(item)"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" @click="deletePackage(item)"><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                                <tr v-if="!filteredPackages.length"><td colspan="7" class="text-center text-muted py-4">Belum ada paket soal.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="showPackageModal" @click.self="showPackageModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-folder-plus text-primary me-2"></i>{{ packageForm.id ? 'Edit Paket Soal' : 'Buat Paket Soal Baru' }}
                    </h5>
                    <button class="btn-close" @click="showPackageModal = false"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Mata Pelajaran</label>
                        <select class="form-select" v-model="packageForm.mata_pelajaran_id" @change="onMapelChange">
                            <option value="">Pilih Mata Pelajaran...</option>
                            <option v-for="mapel in mapels" :key="mapel.id" :value="mapel.id">
                                {{ mapel.nama_mapel }} ({{ mapel.kode_mapel }})
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">KODE PAKET SOAL</label>
                        <input class="form-control" v-model="packageForm.kode_paket" placeholder="Kode otomatis terisi dari Mapel..." disabled style="text-transform: uppercase; background-color: #f8fafc; font-weight: 600; color: #475569;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Soal Pilihan Ganda</label>
                        <input type="number" min="0" class="form-control" v-model="packageForm.jumlah_pg" placeholder="Masukkan jumlah soal PG">
                    </div>
                    <div class="form-check form-switch mt-3 fs-6">
                        <input class="form-check-input" type="checkbox" id="hasIsianSwitch" v-model="packageForm.has_isian">
                        <label class="form-check-label fw-semibold text-dark" for="hasIsianSwitch">Aktifkan Soal Isian / Esai</label>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showPackageModal = false">Batal</button>
                    <button class="btn btn-primary px-4" @click="savePackage">
                        {{ packageForm.id ? 'Simpan Perubahan' : 'Lanjut Isi Soal' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom" v-if="showWizardModal" @click.self="closeWizard">
            <div class="modal-custom modal-fullscreen-custom">
                <div class="modal-header-custom">
                    <div><h5 class="fw-bold mb-1">Kelola Soal: {{ activePackage.judul }}</h5><small class="text-muted">{{ activePackage.nama_mapel }} | {{ activePackage.soal.length }} soal | Status {{ activePackage.status }}</small></div>
                    <div class="d-flex gap-2"><button class="btn btn-outline-success btn-sm" @click="showImportModal = true"><i class="fa-solid fa-file-excel me-1"></i> Import</button><button class="btn btn-success btn-sm" @click="finalizePackage"><i class="fa-solid fa-lock me-1"></i> Finalisasi</button><button class="btn-close" @click="closeWizard"></button></div>
                </div>
                <div class="modal-body-custom p-0 d-flex wizard-body">
                    <div class="question-sidebar">
                        <button class="btn btn-primary w-100 mb-3" @click="newQuestion"><i class="fa-solid fa-plus me-1"></i> Tambah Soal</button>
                        <div v-for="question in activePackage.soal" :key="question.id" class="question-item" :class="{ active: questionForm.id === question.id }" @click="editQuestion(question)">
                            <div class="d-flex justify-content-between"><strong>No. {{ question.urutan }} <span class="badge bg-secondary">{{ question.tipe_soal }}</span></strong><button class="btn btn-link text-danger p-0" @click.stop="deleteQuestion(question)"><i class="fa-solid fa-trash-can"></i></button></div>
                            <div class="small text-muted text-truncate mt-1">{{ question.pertanyaan }}</div>
                        </div>
                        <div v-if="!activePackage.soal.length" class="text-center text-muted small py-4">Belum ada soal.</div>
                    </div>
                    <div class="question-editor">
                        <h5 class="fw-bold text-primary mb-4">{{ questionForm.id ? 'Edit Soal' : 'Tambah Soal Baru' }}</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tipe Soal</label>
                                <div class="form-control bg-light fw-bold text-secondary">
                                    {{ questionForm.tipe_soal === 'PG' ? 'Pilihan Ganda' : 'Isian / Esai' }}
                                </div>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Bobot Nilai</label><input type="number" min="0" step="0.01" class="form-control" v-model="questionForm.bobot_nilai"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Gambar Opsional</label><input ref="imageInput" type="file" accept="image/*" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Pertanyaan</label><textarea class="form-control" rows="4" v-model="questionForm.pertanyaan"></textarea></div>
                        <div v-if="questionForm.tipe_soal === 'PG'" class="option-box">
                            <div class="fw-semibold mb-2">Opsi Jawaban dan Kunci</div>
                            <div v-for="option in questionForm.opsi" :key="option.kode" class="input-group mb-2">
                                <span class="input-group-text"><input type="radio" v-model="questionForm.kunci" :value="option.kode"></span>
                                <span class="input-group-text fw-bold">{{ option.kode }}</span>
                                <input class="form-control" v-model="option.teks_opsi" :placeholder="`Opsi ${option.kode}`">
                            </div>
                            <small class="text-muted">Opsi kosong diabaikan. Isi minimal dua opsi dan pilih satu kunci.</small>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-light" @click="newQuestion">Kosongkan Form</button><button class="btn btn-primary px-4" @click="saveQuestion"><i class="fa-solid fa-check me-1"></i> Simpan Soal</button></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-backdrop-custom import-layer" v-if="showImportModal" @click.self="showImportModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom"><h5 class="fw-bold mb-0">Import Soal Excel</h5><button class="btn-close" @click="showImportModal = false"></button></div>
                <div class="modal-body-custom"><a class="btn btn-outline-primary mb-3" href="/kelola/template-soal"><i class="fa-solid fa-download me-1"></i> Unduh Template</a><input ref="importInput" class="form-control" type="file" accept=".xlsx,.xls,.csv"></div>
                <div class="modal-footer-custom"><button class="btn btn-light" @click="showImportModal = false">Batal</button><button class="btn btn-success" @click="importQuestions">Proses Import</button></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

const packages = ref([]); const mapels = ref([]); const search = ref(''); const statusFilter = ref('');
const showPackageModal = ref(false); const showWizardModal = ref(false); const showImportModal = ref(false);
const packageForm = ref({ id: null, mata_pelajaran_id: '', kode_paket: '', jumlah_pg: 0, has_isian: false });
const activePackage = ref({ id: null, judul: '', nama_mapel: '', status: 'draft', soal: [] });
const questionForm = ref(emptyQuestion()); const imageInput = ref(null); const importInput = ref(null);
const filteredPackages = computed(() => packages.value.filter(item => (!statusFilter.value || item.status === statusFilter.value) && (!search.value || `${item.judul} ${item.nama_mapel}`.toLowerCase().includes(search.value.toLowerCase()))));
const message = error => Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Permintaan gagal diproses.';
function emptyQuestion() { return { id: null, tipe_soal: 'PG', pertanyaan: '', bobot_nilai: 1, kunci: 'A', opsi: ['A','B','C','D','E'].map(kode => ({ kode, teks_opsi: '' })) }; }
const loadData = async () => { const { data } = await axios.get('/kelola/data/paket-soal'); packages.value = data.data.packages; mapels.value = data.data.mapels; };
const openPackage = item => {
    packageForm.value = item ? {
        id: item.id,
        mata_pelajaran_id: item.mata_pelajaran_id,
        kode_paket: item.kode_paket || '',
        jumlah_pg: item.jumlah_pg || 0,
        has_isian: item.has_isian ? true : false
    } : {
        id: null,
        mata_pelajaran_id: '',
        kode_paket: '',
        jumlah_pg: 0,
        has_isian: false
    };
    showPackageModal.value = true;
};
const onMapelChange = () => {
    const selected = mapels.value.find(m => m.id === Number(packageForm.value.mata_pelajaran_id));
    packageForm.value.kode_paket = selected ? selected.kode_mapel : '';
};
const savePackage = async () => {
    if (!packageForm.value.mata_pelajaran_id) {
        return Swal.fire('Perhatian', 'Silakan pilih Mata Pelajaran terlebih dahulu.', 'warning');
    }
    try {
        const payload = {
            mata_pelajaran_id: packageForm.value.mata_pelajaran_id,
            kode_paket: packageForm.value.kode_paket,
            jumlah_pg: Number(packageForm.value.jumlah_pg),
            has_isian: Boolean(packageForm.value.has_isian)
        };
        let res;
        if (packageForm.value.id) {
            res = await axios.put(`/kelola/data/paket-soal/${packageForm.value.id}`, payload);
            showPackageModal.value = false;
            await loadData();
            toast('Paket tersimpan.');
        } else {
            res = await axios.post('/kelola/data/paket-soal', payload);
            showPackageModal.value = false;
            await loadData();
            toast('Paket dibuat.');
            const newId = res.data?.data?.id;
            if (newId) {
                const newPkg = packages.value.find(p => p.id === newId);
                if (newPkg) {
                    openWizard(newPkg);
                }
            }
        }
    } catch (error) {
        Swal.fire('Gagal', message(error), 'error');
    }
};
const deletePackage = async item => { if (!(await Swal.fire({ title: 'Hapus paket?', text: item.judul, icon: 'warning', showCancelButton: true })).isConfirmed) return; try { await axios.delete(`/kelola/data/paket-soal/${item.id}`); await loadData(); toast('Paket dihapus.'); } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const refreshPackage = async id => { const { data } = await axios.get(`/kelola/data/paket-soal/${id}`); activePackage.value = data.data.package; };
const openWizard = async item => { try { await refreshPackage(item.id); newQuestion(); showWizardModal.value = true; } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const closeWizard = async () => { showWizardModal.value = false; await loadData(); };
const newQuestion = () => {
    questionForm.value = emptyQuestion();
    if (activePackage.value) {
        const nextUrutan = activePackage.value.soal.length + 1;
        const limitPg = Number(activePackage.value.jumlah_pg || 0);
        questionForm.value.tipe_soal = nextUrutan <= limitPg ? 'PG' : (activePackage.value.has_isian ? 'ISIAN' : 'PG');
    }
    if (imageInput.value) imageInput.value.value = '';
};
const editQuestion = item => {
    questionForm.value = {
        id: item.id,
        tipe_soal: item.tipe_soal,
        pertanyaan: item.pertanyaan,
        bobot_nilai: item.bobot_nilai,
        kunci: item.opsi.find(option => option.is_benar)?.kode || 'A',
        opsi: ['A','B','C','D','E'].map(kode => ({ kode, teks_opsi: item.opsi.find(option => option.kode === kode)?.teks_opsi || '' }))
    };
    if (imageInput.value) imageInput.value.value = '';
};
const saveQuestion = async () => { try { const form = new FormData(); form.append('tipe_soal', questionForm.value.tipe_soal); form.append('pertanyaan', questionForm.value.pertanyaan); form.append('bobot_nilai', questionForm.value.bobot_nilai); if (imageInput.value?.files[0]) form.append('gambar', imageInput.value.files[0]); if (questionForm.value.tipe_soal === 'PG') questionForm.value.opsi.filter(option => option.teks_opsi.trim()).forEach((option, index) => { form.append(`opsi[${index}][kode]`, option.kode); form.append(`opsi[${index}][teks_opsi]`, option.teks_opsi); form.append(`opsi[${index}][is_benar]`, option.kode === questionForm.value.kunci ? '1' : '0'); }); const url = questionForm.value.id ? `/kelola/data/paket-soal/${activePackage.value.id}/soal/${questionForm.value.id}` : `/kelola/data/paket-soal/${activePackage.value.id}/soal`; await axios.post(url, form); await refreshPackage(activePackage.value.id); newQuestion(); toast('Soal tersimpan.'); } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const deleteQuestion = async item => { if (!(await Swal.fire({ title: 'Hapus soal?', icon: 'warning', showCancelButton: true })).isConfirmed) return; try { await axios.delete(`/kelola/data/paket-soal/${activePackage.value.id}/soal/${item.id}`); await refreshPackage(activePackage.value.id); newQuestion(); } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const finalizePackage = async () => { try { await axios.post(`/kelola/data/paket-soal/${activePackage.value.id}/ready`); await refreshPackage(activePackage.value.id); await loadData(); Swal.fire('Berhasil', 'Paket siap digunakan pada master ujian.', 'success'); } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const importQuestions = async () => { if (!importInput.value?.files[0]) return Swal.fire('Pilih file', 'Pilih file Excel atau CSV terlebih dahulu.', 'warning'); try { const form = new FormData(); form.append('file', importInput.value.files[0]); const { data } = await axios.post(`/kelola/data/paket-soal/${activePackage.value.id}/import`, form); showImportModal.value = false; await refreshPackage(activePackage.value.id); await loadData(); Swal.fire('Import selesai', `${data.data.imported} soal berhasil, ${data.data.failed} gagal.`, data.data.failed ? 'warning' : 'success'); } catch (error) { Swal.fire('Gagal', message(error), 'error'); } };
const toast = title => Swal.fire({ title, icon: 'success', timer: 1200, showConfirmButton: false });
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
onMounted(() => loadData().catch(error => Swal.fire('Gagal', message(error), 'error')));
</script>

<style scoped>
#wrapper { display:flex;width:100vw;min-height:100vh }.main-content{flex-grow:1;min-width:0;height:100vh;overflow-y:auto}.top-navbar{background:#fff;height:70px;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.02);position:sticky;top:0;z-index:1020}.stat-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.04);padding:1.25rem;display:flex;align-items:center}.stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin-right:1rem}.icon-blue{background:#eff6ff;color:#3b82f6}.icon-green{background:#f0fdf4;color:#22c55e}.icon-purple{background:#faf5ff;color:#a855f7}.stat-label{font-size:.75rem;color:#64748b;text-transform:uppercase;font-weight:600}.stat-value{font-size:1.5rem;font-weight:700}.table-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.04);overflow:hidden}.table-card-header{padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;gap:1rem}.table-custom th{background:#f8fafc;color:#475569;padding:.85rem 1rem;font-size:.8rem;text-transform:uppercase}.table-custom td{padding:.85rem 1rem;vertical-align:middle;font-size:.9rem}.modal-backdrop-custom{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1050;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px)}.import-layer{z-index:1060}.modal-custom{background:#fff;border-radius:16px;width:95%;max-width:580px;box-shadow:0 25px 60px rgba(0,0,0,.15);display:flex;flex-direction:column}.modal-fullscreen-custom{max-width:95vw;height:95vh;overflow:hidden}.modal-header-custom,.modal-footer-custom{padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9}.modal-footer-custom{justify-content:flex-end;gap:.75rem;border-top:1px solid #f1f5f9;border-bottom:0}.modal-body-custom{padding:1.5rem}.wizard-body{height:calc(95vh - 70px)}.question-sidebar{width:320px;background:#f8fafc;border-right:1px solid #e2e8f0;padding:1rem;overflow-y:auto}.question-editor{flex-grow:1;padding:1.5rem;overflow-y:auto}.question-item{padding:.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:.5rem;cursor:pointer}.question-item.active{border-color:#3b82f6;background:#eff6ff}.option-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem}
</style>
