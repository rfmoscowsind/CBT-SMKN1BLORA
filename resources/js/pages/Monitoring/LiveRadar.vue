<template>
    <div id="wrapper">
        <AdminSidebar />
        <div class="main-content bg-light">
            
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark me-3">Monitoring Ujian</h5>
                    <span class="badge bg-danger rounded-pill bg-opacity-10 text-danger border border-danger px-3 py-1">
                        <i class="fa-solid fa-tower-broadcast me-1 animate-pulse"></i> Live Radar
                    </span>
                </div>
                <div>
                    <button class="btn btn-outline-danger btn-sm px-3" @click="logout">
                        <i class="fa-solid fa-power-off me-1"></i> Logout
                    </button>
                </div>
            </div>

            <div class="container-fluid p-4">
                
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-tower-broadcast text-primary me-2"></i> Radar Nilai Real-Time
                        </h4>
                        <p class="text-muted small mb-0">Memantau aktivitas pengerjaan, skor sementara, dan status koneksi siswa secara real-time langsung dari Redis memory.</p>
                    </div>
                    <div class="live-badge">
                        <span class="live-dot"></span> LIVE UPDATE
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card shadow-sm">
                            <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Siswa Online</div>
                                <div class="fs-4 fw-bold text-dark">{{ totalSiswaOnline }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stat-card shadow-sm">
                            <div class="stat-icon icon-purple"><i class="fa-solid fa-user-check"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Sesi Aktif Ujian</div>
                                <div class="fs-4 fw-bold text-dark">{{ totalSesiAktif }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stat-card shadow-sm">
                            <div class="stat-icon icon-green"><i class="fa-solid fa-gauge-high"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Rata-Rata Nilai (PG)</div>
                                <div class="fs-4 fw-bold text-dark">{{ avgScore.toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input v-model="searchQuery" type="text" class="form-control bg-light border-0 text-dark" placeholder="Cari nama siswa...">
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <select v-model="classFilter" class="form-select bg-light border-0 text-dark">
                                    <option value="all">Semua Kelas</option>
                                    <option v-for="kelas in kelasList" :key="kelas" :value="kelas">{{ kelas }}</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-12 col-lg-5 text-lg-end">
                                <span class="text-muted small">Menampilkan <strong>{{ filteredPeserta.length }}</strong> dari <strong>{{ radarPeserta.length }}</strong> sesi ujian terkonfirmasi.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="table-card p-4 shadow-sm">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="25%">Nama Peserta</th>
                                            <th width="15%">Kelas</th>
                                            <th width="15%">Status Gawai</th>
                                            <th width="20%">Progress Pengerjaan</th>
                                            <th width="10%">Live Score (PG)</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="filteredPeserta.length === 0">
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-inbox fs-3 mb-2 d-block"></i>
                                                Belum ada siswa yang memulai ujian dengan token valid.
                                            </td>
                                        </tr>
                                        <tr v-for="(peserta, index) in filteredPeserta" :key="peserta.id">
                                            <td>{{ index + 1 }}</td>
                                            <td class="fw-semibold text-dark">{{ peserta.nama }}</td>
                                            <td class="text-secondary">{{ peserta.kelas }}</td>
                                            <td>
                                                <span v-if="peserta.status.includes('Online')" class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                                                    <i class="fa-solid fa-circle text-success" style="font-size: 0.5rem; vertical-align: middle; margin-right: 3px;"></i> {{ peserta.status }}
                                                </span>
                                                <span v-else class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Terputus
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2 text-dark fw-semibold">{{ peserta.progress }} / {{ peserta.total_soal || 40 }}</span>
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-primary" :style="{ width: ((peserta.progress / (peserta.total_soal || 40)) * 100) + '%' }"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="score-cell rounded" :class="{ 'score-updated': peserta.isUpdated }">
                                                {{ peserta.score.toFixed(2) }}
                                            </td>
                                            <td class="text-center">
                                                <button v-if="isSessionId(peserta.id)" @click="confirmReset(peserta)" class="btn btn-danger btn-sm" title="Reset sesi & hapus kunci gawai">
                                                    <i class="fa-solid fa-rotate-left"></i> Reset Sesi
                                                </button>
                                                <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1" style="font-size: 0.75rem;">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AdminSidebar from '../../components/AdminSidebar.vue';

const radarPeserta = ref([]);
const currentUser = ref({ name: 'Loading...', role: 'SuperAdmin' });
const searchQuery = ref('');
const classFilter = ref('all');

let intervalRadar = null;

const isSessionId = (id) => {
    return id && !String(id).startsWith('u_');
};

const fetchUser = async () => {
    try {
        const response = await axios.get('/auth/user', { headers: { 'Accept': 'application/json' } });
        if (response.data) {
            currentUser.value = response.data;
            if (currentUser.value.role !== 'SuperAdmin') {
                Swal.fire({
                    title: 'Akses Ditolak',
                    text: 'Halaman ini hanya dapat diakses oleh SuperAdmin.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '/dashboard';
                });
            }
        }
    } catch (e) {
        console.error('Gagal mengambil data user', e);
        window.location.href = '/dashboard';
    }
};

const fetchRadarData = async () => {
    try {
        const response = await axios.get('/monitoring/radar', { headers: { 'Accept': 'application/json' } });
        if (response.data && Array.isArray(response.data)) {
            // Deteksi perubahan skor untuk animasi highlight
            const newData = response.data;
            newData.forEach(newPeserta => {
                const oldPeserta = radarPeserta.value.find(p => p.id === newPeserta.id);
                if (oldPeserta && oldPeserta.score !== newPeserta.score) {
                    newPeserta.isUpdated = true;
                    setTimeout(() => { newPeserta.isUpdated = false; }, 1500);
                } else {
                    newPeserta.isUpdated = false;
                }
            });
            radarPeserta.value = newData;
        }
    } catch (e) {
        console.error('Gagal mengambil data Radar', e);
    }
};

const startRadar = () => {
    fetchRadarData();
    intervalRadar = setInterval(fetchRadarData, 2000); // Polling setiap 2 detik
};

const confirmReset = (peserta) => {
    Swal.fire({
        title: 'Reset Sesi Ujian?',
        text: `Tindakan ini akan menghentikan paksa sesi ujian aktif untuk "${peserta.nama}" dan menghapus kunci gawai agar siswa bisa login kembali.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Reset Sesi',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            resetSession(peserta);
        }
    });
};

const resetSession = async (peserta) => {
    try {
        const response = await axios.post(`/kelola/sesi/${peserta.id}/reset`, {}, { headers: { 'Accept': 'application/json' } });
        if (response.data?.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: response.data.message || 'Sesi dan kunci gawai berhasil direset.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            // Refresh data immediately
            fetchRadarData();
        } else {
            throw new Error(response.data?.message || 'Terjadi kesalahan.');
        }
    } catch (e) {
        console.error('Gagal reset sesi', e);
        const errMsg = e.response?.data?.message || e.message || 'Gagal mereset sesi ujian.';
        Swal.fire('Gagal', errMsg, 'error');
    }
};

// Computed Stats
const totalSiswaOnline = computed(() => {
    return radarPeserta.value.filter(p => p.status.includes('Online')).length;
});

const totalSesiAktif = computed(() => {
    return radarPeserta.value.filter(p => isSessionId(p.id)).length;
});

const avgScore = computed(() => {
    const active = radarPeserta.value.filter(p => isSessionId(p.id));
    if (active.length === 0) return 0;
    const sum = active.reduce((acc, curr) => acc + curr.score, 0);
    return sum / active.length;
});

// Filters
const filteredPeserta = computed(() => {
    return radarPeserta.value.filter(p => {
        const matchesSearch = p.nama.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesClass = classFilter.value === 'all' || p.kelas === classFilter.value;
        return matchesSearch && matchesClass;
    });
});

const kelasList = computed(() => {
    const list = radarPeserta.value.map(p => p.kelas).filter(Boolean);
    return [...new Set(list)].sort();
});

const logout = () => {
    Swal.fire({
        title: 'Keluar Sistem?',
        text: 'Sesi monitoring Anda akan ditutup.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#3b82f6',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/logout';
        }
    });
};

onMounted(() => {
    fetchUser();
    startRadar();
});

onUnmounted(() => {
    if (intervalRadar) clearInterval(intervalRadar);
});
</script>

<style scoped>
#wrapper {
    display: flex;
    width: 100vw;
    min-height: 100vh;
}
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
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-right: 1.2rem;
}
.icon-blue { background-color: #eff6ff; color: #3b82f6; }
.icon-green { background-color: #f0fdf4; color: #22c55e; }
.icon-purple { background-color: #faf5ff; color: #a855f7; }
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
.live-badge {
    background-color: #fee2e2;
    color: #dc2626;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    border: 1px solid #fca5a5;
}
.live-dot {
    width: 8px;
    height: 8px;
    background-color: #dc2626;
    border-radius: 50%;
    margin-right: 6px;
    animation: pulse 1.5s infinite;
}
@keyframes scoreUpdate {
    0% { background-color: transparent; }
    50% { background-color: #dcfce7; }
    100% { background-color: transparent; }
}
.score-updated {
    animation: scoreUpdate 1.5s ease;
}
.score-cell {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e3a8a;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(220, 38, 38, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}
.btn-sm {
    padding: 0.25rem 0.6rem;
    font-size: 0.8rem;
    border-radius: 6px;
}
</style>
