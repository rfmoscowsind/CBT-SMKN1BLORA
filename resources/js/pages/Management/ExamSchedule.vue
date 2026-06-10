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
                            <button class="btn btn-primary" @click="openBatchModal()">
                                <i class="fa-solid fa-layer-group me-1"></i> Buat Jadwal Batch
                            </button>
                            <button class="btn btn-success" @click="openJadwalModal()">
                                <i class="fa-solid fa-plus me-1"></i> Buat Jadwal Tunggal
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

        <!-- Batch Modal -->
        <div class="modal-backdrop-custom" v-if="showBatchModal" @click.self="showBatchModal = false">
            <div class="modal-custom modal-batch">
                <div class="modal-header-custom">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-layer-group me-2"></i>Buat Jadwal Batch</h5>
                        <small class="text-muted">Satu token, banyak grup, preview dulu sebelum dibuat.</small>
                    </div>
                    <button class="btn-close" @click="showBatchModal = false"></button>
                </div>
                <div class="modal-body-custom batch-body">
                    <div class="batch-grid">
                        <div class="batch-main">
                            <div class="batch-step">
                                <div class="step-marker">1</div>
                                <div class="step-content">
                                    <div class="step-title">
                                        <div>
                                            <h6 class="fw-bold mb-0">Default Batch</h6>
                                            <small class="text-muted">Dipakai semua grup kecuali grup override.</small>
                                        </div>
                                        <span class="badge text-bg-light">{{ selectedTingkatName || 'Tingkat belum dipilih' }}</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <label class="form-label fw-semibold">Nama Batch <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" v-model="batchForm.nama_batch" placeholder="cth: Ujian Kelas X Hari Senin Sesi 1">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">Tingkat <span class="text-danger">*</span></label>
                                            <select class="form-select" v-model="batchForm.tingkat">
                                                <option value="">Pilih Tingkat</option>
                                                <option v-for="t in tingkatList" :key="t.id" :value="t.id">{{ t.nama }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Mulai Default</label>
                                            <input type="datetime-local" class="form-control" v-model="batchForm.default_waktu_mulai">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Selesai Default</label>
                                            <input type="datetime-local" class="form-control" v-model="batchForm.default_waktu_selesai">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Durasi Default</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" v-model="batchForm.default_durasi_menit" min="1" placeholder="90">
                                                <span class="input-group-text">menit</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="batch-options mt-3">
                                        <label class="option-chip">
                                            <input type="checkbox" v-model="batchForm.default_acak_soal">
                                            <span><i class="fa-solid fa-shuffle"></i> Acak Soal</span>
                                        </label>
                                        <label class="option-chip">
                                            <input type="checkbox" v-model="batchForm.default_acak_opsi">
                                            <span><i class="fa-solid fa-list-ol"></i> Acak Opsi</span>
                                        </label>
                                        <label class="option-chip">
                                            <input type="checkbox" v-model="batchForm.default_tampilkan_nilai_akhir">
                                            <span><i class="fa-solid fa-square-poll-vertical"></i> Tampil Nilai</span>
                                        </label>
                                        <select class="form-select result-select" v-model="batchForm.default_hasil_visibilitas">
                                            <option value="instant">Hasil langsung</option>
                                            <option value="manual">Hasil manual</option>
                                            <option value="scheduled">Hasil terjadwal</option>
                                        </select>
                                    </div>

                                    <div class="mt-3" v-if="batchForm.default_hasil_visibilitas === 'scheduled'">
                                        <label class="form-label fw-semibold">Tanggal Rilis Hasil Default</label>
                                        <input type="datetime-local" class="form-control" v-model="batchForm.default_tanggal_rilis_hasil">
                                    </div>
                                </div>
                            </div>

                            <div class="batch-step">
                                <div class="step-marker">2</div>
                                <div class="step-content">
                                    <div class="step-title">
                                        <div>
                                            <h6 class="fw-bold mb-0">Tambah Grup</h6>
                                            <small class="text-muted">Pilih jurusan, rombel, dan paket soal untuk satu kelompok jadwal.</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="clearGrupForm">
                                            <i class="fa-solid fa-eraser me-1"></i> Reset
                                        </button>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">Jurusan <span class="text-danger">*</span></label>
                                            <select class="form-select" v-model="grupForm.jurusan_id">
                                                <option value="">Pilih Jurusan</option>
                                                <option v-for="j in jurusanList" :key="j.id" :value="j.id">{{ j.kode_jurusan }} - {{ j.nama_jurusan }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="form-label fw-semibold">Paket Soal <span class="text-danger">*</span></label>
                                            <select class="form-select" v-model="grupForm.paket_soal_id">
                                                <option value="">Pilih Paket Soal</option>
                                                <option v-for="p in paketList" :key="p.id" :value="p.id">{{ p.nama_mapel }} - {{ p.judul }} ({{ p.jumlah_soal }} soal)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                            <label class="form-label fw-semibold mb-0">Rombel <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-light border" type="button" @click="selectAllRombels">Pilih semua</button>
                                                <button class="btn btn-sm btn-light border" type="button" @click="grupForm.rombel_ids = []">Kosongkan</button>
                                            </div>
                                        </div>
                                        <div class="rombel-picker">
                                            <label
                                                v-for="r in rombelList"
                                                :key="r.id"
                                                class="rombel-chip"
                                                :class="{ active: grupForm.rombel_ids.includes(r.id) }"
                                            >
                                                <input type="checkbox" :value="r.id" v-model="grupForm.rombel_ids">
                                                <span>{{ r.nama_rombel }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="override-toggle mt-3">
                                        <div>
                                            <strong>Pengaturan khusus grup</strong>
                                            <small class="text-muted d-block">Aktifkan hanya kalau grup ini beda jam, durasi, atau hasil.</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" v-model="grupForm.override" id="swOverride">
                                        </div>
                                    </div>

                                    <div v-if="grupForm.override" class="override-section mt-3">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Mulai Khusus</label>
                                                <input type="datetime-local" class="form-control" v-model="grupForm.waktu_mulai">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Selesai Khusus</label>
                                                <input type="datetime-local" class="form-control" v-model="grupForm.waktu_selesai">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Durasi Khusus</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" v-model="grupForm.durasi_menit" min="1">
                                                    <span class="input-group-text">menit</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="batch-options mt-3">
                                            <label class="option-chip">
                                                <input type="checkbox" v-model="grupForm.acak_soal">
                                                <span><i class="fa-solid fa-shuffle"></i> Acak Soal</span>
                                            </label>
                                            <label class="option-chip">
                                                <input type="checkbox" v-model="grupForm.acak_opsi">
                                                <span><i class="fa-solid fa-list-ol"></i> Acak Opsi</span>
                                            </label>
                                            <label class="option-chip">
                                                <input type="checkbox" v-model="grupForm.tampilkan_nilai_akhir">
                                                <span><i class="fa-solid fa-square-poll-vertical"></i> Tampil Nilai</span>
                                            </label>
                                            <select class="form-select result-select" v-model="grupForm.hasil_visibilitas">
                                                <option value="instant">Hasil langsung</option>
                                                <option value="manual">Hasil manual</option>
                                                <option value="scheduled">Hasil terjadwal</option>
                                            </select>
                                        </div>
                                        <div class="mt-3" v-if="grupForm.hasil_visibilitas === 'scheduled'">
                                            <label class="form-label fw-semibold">Tanggal Rilis Hasil</label>
                                            <input type="datetime-local" class="form-control" v-model="grupForm.tanggal_rilis_hasil">
                                        </div>
                                    </div>

                                    <button class="btn btn-success w-100 mt-3" @click="tambahGrup">
                                        <i class="fa-solid fa-plus me-1"></i> Tambah ke Daftar Grup
                                    </button>
                                </div>
                            </div>

                            <div class="batch-step">
                                <div class="step-marker">3</div>
                                <div class="step-content">
                                    <div class="step-title">
                                        <div>
                                            <h6 class="fw-bold mb-0">Daftar Grup</h6>
                                            <small class="text-muted">Preview akan dibuat dari daftar ini.</small>
                                        </div>
                                        <span class="badge text-bg-info">{{ batchGroups.length }} grup</span>
                                    </div>

                                    <div v-if="batchGroups.length === 0" class="empty-state">
                                        <i class="fa-solid fa-layer-group"></i>
                                        <span>Belum ada grup. Tambahkan minimal satu grup untuk membuat preview.</span>
                                    </div>
                                    <div v-else class="group-list">
                                        <div v-for="(g, idx) in batchGroups" :key="idx" class="group-row">
                                            <div class="group-index">{{ idx + 1 }}</div>
                                            <div class="group-info">
                                                <strong>{{ getJurusanName(g.jurusan_id) }} / {{ getRombelNames(g.rombel_ids) }}</strong>
                                                <span>{{ getPaketName(g.paket_soal_id) }}</span>
                                                <small>{{ getGroupScheduleLabel(g) }} · {{ g.override ? 'Khusus' : 'Default batch' }}</small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus grup" @click="hapusGrup(idx)">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="batch-sidebar">
                            <div class="summary-panel">
                                <div class="summary-token">
                                    <span>Token Batch</span>
                                    <code v-if="batchForm.gunakan_token">{{ batchForm.token || 'BELUM ADA' }}</code>
                                    <code v-else>NONAKTIF</code>
                                </div>
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" v-model="batchForm.gunakan_token" id="swToken">
                                    <label class="form-check-label fw-semibold" for="swToken">Gunakan token</label>
                                </div>
                                <div v-if="batchForm.gunakan_token" class="input-group mt-2">
                                    <input type="text" class="form-control" v-model="batchForm.token" placeholder="TOKEN" maxlength="20">
                                    <button class="btn btn-outline-secondary" @click="generateBatchToken" type="button" title="Generate token">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </div>

                                <div class="summary-metrics">
                                    <div>
                                        <strong>{{ batchGroups.length }}</strong>
                                        <span>Grup</span>
                                    </div>
                                    <div>
                                        <strong>{{ selectedRombelCount }}</strong>
                                        <span>Rombel dipilih</span>
                                    </div>
                                    <div>
                                        <strong>{{ batchPreview.length }}</strong>
                                        <span>Jadwal preview</span>
                                    </div>
                                </div>

                                <div class="summary-line">
                                    <span>Tingkat</span>
                                    <strong>{{ selectedTingkatName || '-' }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Waktu default</span>
                                    <strong>{{ defaultScheduleLabel }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Hasil</span>
                                    <strong>{{ resultVisibilityLabel(batchForm.default_hasil_visibilitas) }}</strong>
                                </div>

                                <button class="btn btn-warning w-100 mt-3" @click="previewBatch" :disabled="batchGroups.length === 0">
                                    <i class="fa-solid fa-eye me-1"></i> Preview Jadwal
                                </button>
                            </div>

                            <div class="summary-panel preview-panel mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0">Hasil Preview</h6>
                                    <span class="badge text-bg-warning">{{ batchPreview.length }}</span>
                                </div>
                                <div v-if="batchPreview.length === 0" class="empty-preview">
                                    Preview akan muncul setelah tombol Preview Jadwal ditekan.
                                </div>
                                <div v-else class="preview-list">
                                    <div v-for="(item, idx) in batchPreview" :key="idx" class="preview-item">
                                        <strong>{{ idx + 1 }}. {{ item.nama_kelas }}</strong>
                                        <span>{{ item.nama_mapel }} - {{ item.paket_judul }}</span>
                                        <small>{{ item.waktu_mulai }} s/d {{ item.waktu_selesai }}</small>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="btn btn-light" @click="showBatchModal = false">Batal</button>
                    <button class="btn btn-primary" @click="submitBatch" :disabled="batchPreview.length === 0">
                        <i class="fa-solid fa-check me-1"></i> Buat {{ batchPreview.length }} Jadwal
                    </button>
                </div>
            </div>
        </div>

        <!-- Jadwal Modal -->
        <div class="modal-backdrop-custom" v-if="showJadwalModal" @click.self="showJadwalModal = false">
            <div class="modal-custom">
                <div class="modal-header-custom">
                    <h5 class="fw-bold mb-0">{{ editingJadwal ? 'Edit Jadwal' : 'Buat Jadwal Tunggal' }}</h5>
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

const activeTab = ref('jadwal');
const showMasterModal = ref(false);
const showJadwalModal = ref(false);
const showBatchModal = ref(false);
const editingMaster = ref(null);
const editingJadwal = ref(null);

const masterForm = ref({ judul: '', paket_soal_id: '', acakSoal: true, acakOpsi: false, tampilNilai: false, hasil_visibilitas: 'instant', tanggal_rilis_hasil: '' });
const jadwalForm = ref({ master_ujian_id: '', kelas_aktif_id: '', mulai: '', selesai: '', durasi: 60 });
const batchForm = ref({
    nama_batch: '',
    tingkat: '',
    gunakan_token: true,
    token: '',
    default_waktu_mulai: '',
    default_waktu_selesai: '',
    default_durasi_menit: 90,
    default_acak_soal: true,
    default_acak_opsi: false,
    default_tampilkan_nilai_akhir: false,
    default_hasil_visibilitas: 'manual',
    default_tanggal_rilis_hasil: '',
});
const grupForm = ref({
    jurusan_id: '',
    rombel_ids: [],
    paket_soal_id: '',
    override: false,
    waktu_mulai: '',
    waktu_selesai: '',
    durasi_menit: 90,
    acak_soal: true,
    acak_opsi: false,
    tampilkan_nilai_akhir: false,
    hasil_visibilitas: 'manual',
    tanggal_rilis_hasil: '',
});
const batchGroups = ref([]);
const batchPreview = ref([]);

const paketList = ref([]);
const kelasList = ref([]);
const tingkatList = ref([]);
const jurusanList = ref([]);
const rombelList = ref([]);
const masterList = ref([]);
const jadwalList = ref([]);
const selectedJadwalIds = ref([]);

const isAllJadwalSelected = computed(() =>
    jadwalList.value.length > 0 && selectedJadwalIds.value.length === jadwalList.value.length
);
const isSomeJadwalSelected = computed(() =>
    selectedJadwalIds.value.length > 0 && selectedJadwalIds.value.length < jadwalList.value.length
);
const selectedTingkatName = computed(() =>
    tingkatList.value.find(t => String(t.id) === String(batchForm.value.tingkat))?.nama || ''
);
const selectedRombelCount = computed(() =>
    batchGroups.value.reduce((total, group) => total + group.rombel_ids.length, 0)
);
const defaultScheduleLabel = computed(() => {
    if (!batchForm.value.default_waktu_mulai || !batchForm.value.default_waktu_selesai) return '-';
    return `${formatDateTimeLabel(batchForm.value.default_waktu_mulai)} - ${formatDateTimeLabel(batchForm.value.default_waktu_selesai)} (${batchForm.value.default_durasi_menit} menit)`;
});
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
    tingkatList.value = response.data.data.tingkats || [];
    jurusanList.value = response.data.data.jurusans || [];
    rombelList.value = response.data.data.rombels || [];
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

const openBatchModal = () => {
    batchForm.value.token = generateRandomToken();
    batchGroups.value = [];
    batchPreview.value = [];
    showBatchModal.value = true;
};

const generateBatchToken = () => {
    batchForm.value.token = generateRandomToken();
};

const generateRandomToken = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let token = '';
    for (let i = 0; i < 6; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return token;
};

const defaultGrupForm = () => ({
    jurusan_id: '',
    rombel_ids: [],
    paket_soal_id: '',
    override: false,
    waktu_mulai: '',
    waktu_selesai: '',
    durasi_menit: 90,
    acak_soal: true,
    acak_opsi: false,
    tampilkan_nilai_akhir: false,
    hasil_visibilitas: 'manual',
    tanggal_rilis_hasil: '',
});

const clearGrupForm = () => {
    grupForm.value = defaultGrupForm();
};

const selectAllRombels = () => {
    grupForm.value.rombel_ids = rombelList.value.map(r => r.id);
};

const tambahGrup = () => {
    if (!grupForm.value.jurusan_id || grupForm.value.rombel_ids.length === 0 || !grupForm.value.paket_soal_id) {
        notifyError('Jurusan, rombel, dan paket soal wajib diisi.');
        return;
    }
    batchGroups.value.push({ ...grupForm.value, rombel_ids: [...grupForm.value.rombel_ids] });
    batchPreview.value = [];
    clearGrupForm();
    notifySuccess('Berhasil', 'Grup ditambahkan.');
};

const hapusGrup = idx => {
    batchGroups.value.splice(idx, 1);
    batchPreview.value = [];
};

const getJurusanName = id => jurusanList.value.find(j => j.id === id)?.kode_jurusan || '-';
const getRombelNames = ids => ids.map(id => rombelList.value.find(r => r.id === id)?.nama_rombel || '?').join(', ');
const getPaketName = id => paketList.value.find(p => p.id === id)?.judul || '-';
const resultVisibilityLabel = value => ({
    instant: 'Langsung',
    manual: 'Manual',
    scheduled: 'Terjadwal',
}[value] || '-');
const formatDateTimeLabel = value => {
    if (!value) return '-';
    return value.replace('T', ' ').slice(0, 16);
};
const getGroupScheduleLabel = group => {
    if (!group.override) return defaultScheduleLabel.value;
    if (!group.waktu_mulai || !group.waktu_selesai) return 'Waktu khusus belum lengkap';
    return `${formatDateTimeLabel(group.waktu_mulai)} - ${formatDateTimeLabel(group.waktu_selesai)} (${group.durasi_menit} menit)`;
};

const previewBatch = async () => {
    try {
        const payload = {
            header: {
                nama_batch: batchForm.value.nama_batch,
                tingkat: batchForm.value.tingkat,
                gunakan_token: batchForm.value.gunakan_token,
                token: batchForm.value.token,
                default_waktu_mulai: batchForm.value.default_waktu_mulai,
                default_waktu_selesai: batchForm.value.default_waktu_selesai,
                default_durasi_menit: batchForm.value.default_durasi_menit,
                default_acak_soal: batchForm.value.default_acak_soal,
                default_acak_opsi: batchForm.value.default_acak_opsi,
                default_tampilkan_nilai_akhir: batchForm.value.default_tampilkan_nilai_akhir,
                default_hasil_visibilitas: batchForm.value.default_hasil_visibilitas,
                default_tanggal_rilis_hasil: batchForm.value.default_hasil_visibilitas === 'scheduled' ? batchForm.value.default_tanggal_rilis_hasil : null,
            },
            groups: batchGroups.value,
        };
        const response = await axios.post('/kelola/data/jadwal-ujian/batch/preview', payload);
        batchPreview.value = response.data.data.items;
        notifySuccess('Preview Siap', `${response.data.data.count} jadwal akan dibuat.`);
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

const submitBatch = async () => {
    if (!(await confirmAction({
        title: `Buat ${batchPreview.value.length} jadwal?`,
        text: 'Semua jadwal akan dibuat dengan token yang sama.',
        confirmButtonText: 'Ya, buat batch',
    }))) return;
    try {
        const payload = {
            header: {
                nama_batch: batchForm.value.nama_batch,
                tingkat: batchForm.value.tingkat,
                gunakan_token: batchForm.value.gunakan_token,
                token: batchForm.value.token,
                default_waktu_mulai: batchForm.value.default_waktu_mulai,
                default_waktu_selesai: batchForm.value.default_waktu_selesai,
                default_durasi_menit: batchForm.value.default_durasi_menit,
                default_acak_soal: batchForm.value.default_acak_soal,
                default_acak_opsi: batchForm.value.default_acak_opsi,
                default_tampilkan_nilai_akhir: batchForm.value.default_tampilkan_nilai_akhir,
                default_hasil_visibilitas: batchForm.value.default_hasil_visibilitas,
                default_tanggal_rilis_hasil: batchForm.value.default_hasil_visibilitas === 'scheduled' ? batchForm.value.default_tanggal_rilis_hasil : null,
            },
            groups: batchGroups.value,
        };
        const response = await axios.post('/kelola/data/jadwal-ujian/batch', payload);
        showBatchModal.value = false;
        await loadData();
        notifySuccess('Berhasil', `${response.data.data.count} jadwal batch dibuat.`);
        activeTab.value = 'jadwal';
    } catch (error) {
        notifyError(errorMessage(error));
    }
};

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
.modal-batch { width: min(1180px, 96vw); max-width: 1180px; }
.batch-body { max-height: 74vh; overflow-y: auto; background: #f8fafc; }
.batch-grid { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 1rem; align-items: start; }
.batch-main { display: grid; gap: 1rem; }
.batch-step { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 0.75rem; }
.step-marker { width: 34px; height: 34px; border-radius: 50%; background: #1e3a8a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 8px 18px rgba(30,58,138,0.18); }
.step-content, .summary-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
.step-title { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.batch-options { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; }
.option-chip { margin: 0; cursor: pointer; }
.option-chip input, .rombel-chip input { position: absolute; opacity: 0; pointer-events: none; }
.option-chip span { min-height: 38px; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #334155; font-weight: 600; font-size: 0.86rem; }
.option-chip input:checked + span { border-color: #15803d; background: #ecfdf5; color: #166534; }
.result-select { width: auto; min-width: 170px; }
.rombel-picker { display: flex; flex-wrap: wrap; gap: 0.55rem; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; max-height: 142px; overflow-y: auto; }
.rombel-chip { margin: 0; cursor: pointer; }
.rombel-chip span { min-width: 52px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center; padding: 0.35rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #334155; font-weight: 700; }
.rombel-chip.active span { border-color: #0f766e; background: #ccfbf1; color: #115e59; }
.override-toggle { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff7ed; }
.override-section { border-left: 4px solid #0f766e; background: #f8fafc; border-radius: 8px; padding: 1rem; }
.empty-state, .empty-preview { display: flex; align-items: center; gap: 0.75rem; min-height: 72px; padding: 1rem; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; background: #f8fafc; }
.empty-state i { font-size: 1.2rem; color: #94a3b8; }
.group-list { display: grid; gap: 0.65rem; }
.group-row { display: grid; grid-template-columns: 32px minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
.group-index { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #e0f2fe; color: #075985; font-weight: 700; }
.group-info { min-width: 0; display: grid; gap: 0.1rem; }
.group-info strong, .group-info span, .group-info small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.group-info span { color: #334155; }
.group-info small { color: #64748b; }
.batch-sidebar { position: sticky; top: 0; display: grid; gap: 1rem; }
.summary-token { display: grid; gap: 0.35rem; }
.summary-token span { color: #64748b; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; }
.summary-token code { display: block; padding: 0.7rem 0.85rem; border-radius: 8px; background: #111827; color: #fde68a; font-size: 1.25rem; font-weight: 800; text-align: center; letter-spacing: 0; }
.summary-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin: 1rem 0; }
.summary-metrics div { padding: 0.65rem 0.45rem; border-radius: 8px; background: #f1f5f9; text-align: center; }
.summary-metrics strong { display: block; font-size: 1.2rem; line-height: 1; color: #0f172a; }
.summary-metrics span { display: block; margin-top: 0.25rem; font-size: 0.72rem; color: #64748b; }
.summary-line { display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.55rem 0; border-top: 1px solid #e2e8f0; font-size: 0.86rem; }
.summary-line span { color: #64748b; }
.summary-line strong { color: #0f172a; text-align: right; }
.preview-panel { max-height: 320px; display: flex; flex-direction: column; }
.preview-list { overflow-y: auto; display: grid; gap: 0.5rem; padding-right: 0.25rem; }
.preview-item { display: grid; gap: 0.15rem; padding: 0.65rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
.preview-item span { color: #334155; font-size: 0.82rem; }
.preview-item small { color: #64748b; }
.modal-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body-custom { padding: 1.5rem; } .modal-footer-custom { padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 0.75rem; }
@media (max-width: 992px) {
    .modal-batch { width: 96vw; }
    .batch-grid { grid-template-columns: 1fr; }
    .batch-sidebar { position: static; }
}
@media (max-width: 576px) {
    .top-navbar { padding: 0 1rem; }
    .batch-step { grid-template-columns: 1fr; }
    .step-marker { display: none; }
    .step-title, .override-toggle { flex-direction: column; align-items: stretch; }
    .result-select { width: 100%; }
    .summary-metrics { grid-template-columns: 1fr; }
    .modal-footer-custom { flex-direction: column-reverse; }
    .modal-footer-custom .btn { width: 100%; }
}
@keyframes modalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
</style>
