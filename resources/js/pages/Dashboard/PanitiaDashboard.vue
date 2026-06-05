<template>
    <div id="wrapper">
        <div class="sidebar">
            <div class="sidebar-brand">
                <img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo">
                <div>
                    <div class="fw-bold fs-6 text-white">SMKN 1 BLORA</div>
                    <div style="font-size: 0.7rem; color: #bfdbfe;">Panel Admin / Panitia</div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <div class="px-4 text-uppercase fw-bold mb-2 mt-2" style="font-size: 0.7rem; color: #93c5fd;">Menu Utama</div>
                <router-link to="/vue/dashboard/panitia" class="nav-item-custom active"><i class="fa-solid fa-house"></i> Dashboard Overview</router-link>
                
                <div class="px-4 text-uppercase fw-bold mb-2 mt-4" style="font-size: 0.7rem; color: #93c5fd;">Operasional</div>
                <router-link to="/vue/management/master" class="nav-item-custom"><i class="fa-solid fa-school"></i> Master Sekolah</router-link>
                <router-link to="/vue/management/siswa" class="nav-item-custom"><i class="fa-solid fa-users"></i> Manajemen Siswa</router-link>
                <router-link to="/vue/management/jadwal" class="nav-item-custom"><i class="fa-solid fa-calendar-check"></i> Manajemen Jadwal</router-link>
                
                <div class="px-4 text-uppercase fw-bold mb-2 mt-4" style="font-size: 0.7rem; color: #93c5fd;">Akademik & Hasil</div>
                <router-link to="/vue/management/soal" class="nav-item-custom"><i class="fa-solid fa-book-open"></i> Bank Soal</router-link>
                <router-link to="/vue/management/hasil" class="nav-item-custom"><i class="fa-solid fa-file-export"></i> Hasil Ujian</router-link>
                <router-link to="/vue/management/download-hasil" class="nav-item-custom"><i class="fa-solid fa-file-pdf"></i> Download PDF Hasil</router-link>
            </div>
            
            <div class="p-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                <a href="#" class="nav-item-custom p-0" @click.prevent="bukaProfil">
                    <div class="d-flex align-items-center w-100">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center text-primary me-2" style="width: 35px; height: 35px;">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div class="small flex-grow-1">
                            <div class="text-white fw-bold">{{ currentUser.name }}</div>
                            <div style="font-size: 0.7rem; color: #bfdbfe;">Lihat Profil</div>
                        </div>
                        <i class="fa-solid fa-gear text-white opacity-50" style="width: auto;"></i>
                    </div>
                </a>
            </div>
        </div>

        <div class="main-content">
            
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark me-3">Dashboard Panitia</h5>
                    <span class="text-muted small d-none d-md-block">Tahun Ajaran 2025/2026 Genap</span>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Keluar
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                
                 <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card primary">
                            <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Total Siswa Aktif</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.total_siswa }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card info">
                            <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Jadwal Hari Ini</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.ujian_berlangsung }} Sesi</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card success">
                            <div class="stat-icon"><i class="fa-solid fa-file-circle-check"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Bank Soal Tersedia</div>
                                <div class="fs-4 fw-bold text-dark">{{ stats.total_paket }} Paket</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card warning">
                            <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation text-warning"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Antrean Reset Sesi</div>
                                <div class="fs-4 fw-bold text-danger">{{ sesiBermasalah.length }} Siswa</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="table-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-rotate-left text-danger me-2"></i>Butuh Reset Sesi</h5>
                                    <p class="text-muted small mb-0">Siswa yang terputus / ganti gawai saat ujian berlangsung.</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>Siswa</th>
                                            <th>Kelas</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="sesiBermasalah.length === 0">
                                            <td colspan="3" class="text-center text-muted py-4">Tidak ada laporan sesi bermasalah.</td>
                                        </tr>
                                        <tr v-for="siswa in sesiBermasalah" :key="siswa.id">
                                            <td>
                                                <div class="fw-semibold text-dark">{{ siswa.name }}</div>
                                                <div class="small text-muted">{{ siswa.mapel }}</div>
                                            </td>
                                            <td>{{ siswa.kelas }}</td>
                                            <td class="text-end">
                                                <button class="btn btn-danger btn-action" @click="resetSesi(siswa)">
                                                    Reset Login
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="#" class="text-decoration-none small text-primary fw-semibold">Lihat Semua Data Siswa <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="table-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-export text-success me-2"></i>Siap Export</h5>
                                    <p class="text-muted small mb-0">Jadwal ujian yang telah selesai dan siap diunduh hasilnya.</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>Mata Pelajaran</th>
                                            <th>Peserta</th>
                                            <th class="text-end">Unduh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="hasil in ujianSelesai" :key="hasil.id">
                                            <td>
                                                <div class="fw-semibold text-dark">{{ hasil.mapel }}</div>
                                                <div class="small text-muted">{{ hasil.tanggal }}</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                    {{ hasil.jumlah_peserta }} Siswa
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-primary btn-action dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Export
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                                        <li><a class="dropdown-item py-2" href="#" @click.prevent="exportData(hasil.id, 'excel')"><i class="fa-solid fa-file-excel text-success me-2"></i> Format Excel (.xlsx)</a></li>
                                                        <li><a class="dropdown-item py-2" href="#" @click.prevent="exportData(hasil.id, 'pdf')"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Format PDF (.pdf)</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="#" class="text-decoration-none small text-primary fw-semibold">Buka Menu Export Lengkap <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const sesiBermasalah = ref([]);
const ujianSelesai = ref([]);
const stats = ref({
    total_siswa: 0,
    ujian_berlangsung: 0,
    peserta_aktif: 0,
    total_paket: 0
});
const currentUser = ref({ name: 'Loading...', role: 'Panitia' });

let intervalId = null;

const fetchUser = async () => {
    try {
        const response = await axios.get('/auth/user', { headers: { 'Accept': 'application/json' } });
        if (response.data) {
            currentUser.value = response.data;
        }
    } catch (e) {
        console.error('Gagal mengambil data user', e);
    }
};

const fetchDashboardData = async () => {
    try {
        // Fetch stats
        const resStats = await axios.get('/monitoring/stats', { headers: { 'Accept': 'application/json' } });
        if (resStats.data) {
            stats.value = resStats.data;
        }

        // Fetch sessions
        const resSessions = await axios.get('/monitoring/sessions', { headers: { 'Accept': 'application/json' } });
        if (resSessions.data && Array.isArray(resSessions.data)) {
            // Sesi aktif/terkunci yang bisa di-reset tanpa menghapus jawaban.
            sesiBermasalah.value = resSessions.data.filter(s => ['aktif', 'terkunci'].includes(s.status));

            // Ujian selesai (siap export)
            const finished = resSessions.data.filter(s => s.status === 'selesai');
            const uniqueFinished = [];
            const seenMapel = new Set();
            finished.forEach(s => {
                if (!seenMapel.has(s.mapel)) {
                    seenMapel.add(s.mapel);
                    
                    let formattedDate = '-';
                    if (s.waktu_submit) {
                        formattedDate = new Date(s.waktu_submit).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
                    }

                    uniqueFinished.push({
                        id: s.jadwal_ujian_id,
                        mapel: s.mapel,
                        tanggal: formattedDate,
                        jumlah_peserta: finished.filter(f => f.mapel === s.mapel).length
                    });
                }
            });
            ujianSelesai.value = uniqueFinished;
        }
    } catch (e) {
        console.error('Gagal memuat data dashboard panitia', e);
    }
};

const resetSesi = (siswa) => {
    Swal.fire({
        title: 'Reset Sesi Ujian?',
        html: `Anda akan mereset status login ujian atas nama <br><b>${siswa.name} (${siswa.kelas})</b>. <br><br>Jawaban sebelumnya tetap aman.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Reset Sekarang',
        cancelButtonText: 'Batal'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await axios.post(`/kelola/sesi/${siswa.id}/reset`, {}, { headers: { 'Accept': 'application/json' } });
                if (response.data && response.data.success) {
                    Swal.fire(
                        'Berhasil di-Reset!',
                        'Siswa kini dapat login kembali dari gawainya.',
                        'success'
                    );
                    fetchDashboardData();
                }
            } catch (e) {
                Swal.fire('Error', 'Gagal mereset sesi siswa.', 'error');
            }
        }
    });
};

const exportData = (jadwal_id, tipe) => {
    // FIXED: Tidak lagi menggunakan mock setTimeout.
    // Redirect ke endpoint download nyata /kelola/laporan/{id}/{format}
    if (!jadwal_id) {
        Swal.fire({
            icon: 'info',
            title: 'Export Hasil Ujian',
            html: 'Untuk download hasil ujian, gunakan menu <b>Download Hasil</b> di sidebar.<br><br>Di sana Anda bisa preview dan download PDF hasil ujian per kelas.',
            confirmButtonColor: '#1e3a8a',
            confirmButtonText: 'Buka Halaman Download'
        }).then(result => {
            if (result.isConfirmed) {
                window.location.href = '/vue/management/download-hasil';
            }
        });
        return;
    }
    const format = tipe === 'excel' ? 'xlsx' : 'pdf';
    Swal.fire({
        title: 'Menyiapkan Laporan',
        text: `File ${format.toUpperCase()} sedang di-generate...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    window.location.href = `/kelola/laporan/${jadwal_id}/${format}`;
    // Tutup Swal setelah browser mulai download (redirect akan close window)
    setTimeout(() => { if (Swal.isVisible()) Swal.close(); }, 2000);
};

const bukaProfil = () => {
    Swal.fire({
        title: 'Profil Pengguna',
        html: `
            <div class="text-start mt-3">
                <p><b>Nama:</b> ${currentUser.value.name}</p>
                <p><b>Jabatan:</b> ${currentUser.value.role} Ujian / Tata Usaha</p>
                <p><b>Hak Akses:</b> Mengelola Jadwal, Siswa, dan Laporan Hasil.</p>
            </div>
        `,
        icon: 'info',
        confirmButtonColor: '#1e3a8a'
    });
};

const logout = () => {
    Swal.fire({
        title: 'Keluar Sistem?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/logout';
        }
    });
};

onMounted(() => {
    fetchUser();
    fetchDashboardData();
    intervalId = setInterval(fetchDashboardData, 5000); // Polling setiap 5 detik
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<style scoped>
#wrapper {
    display: flex;
    width: 100vw;
    min-height: 100vh;
}
.sidebar {
    width: 280px;
    background-color: #1e3a8a; 
    color: #f8fafc;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.3s;
    z-index: 1030;
}
.sidebar-brand {
    padding: 1.5rem;
    background-color: #1e3a8a;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #3b82f6;
}
.sidebar-brand img { width: 40px; margin-right: 15px; }
.sidebar-nav { padding: 1rem 0; flex-grow: 1; }
.nav-item-custom {
    padding: 0.8rem 1.5rem;
    color: #bfdbfe;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 4px solid transparent;
}
.nav-item-custom:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.1);
}
.nav-item-custom.active {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.15);
    border-left: 4px solid #60a5fa;
    font-weight: 600;
}
.nav-item-custom i { width: 25px; font-size: 1.1rem; }
.main-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: 100vh;
    overflow-y: auto;
}
.top-navbar {
    background-color: #ffffff;
    height: 70px;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    position: sticky;
    top: 0;
    z-index: 1020;
}
.stat-card {
    background: #fff;
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    border-left: 5px solid #e2e8f0;
}
.stat-card.primary { border-left-color: #3b82f6; }
.stat-card.success { border-left-color: #22c55e; }
.stat-card.warning { border-left-color: #eab308; }
.stat-card.info { border-left-color: #0ea5e9; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 1.2rem;
    background-color: #f1f5f9;
    color: #64748b;
}

.table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: none;
}
.table-custom thead th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
    padding: 1rem;
}
.table-custom tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}

.btn-action {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    border-radius: 6px;
    font-weight: 500;
}
</style>
