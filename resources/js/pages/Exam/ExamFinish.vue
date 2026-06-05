<template>
  <div class="finish-wrapper container-fluid p-4 d-flex align-items-center justify-content-center">
    <div v-if="isLoading" class="text-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Memuat hasil ujian...</p>
    </div>
    <div v-else class="card finish-card border-0 shadow-lg text-center p-4 p-md-5" style="max-width: 600px; width: 100%;">
        <div class="mb-4">
            <div class="icon-circle bg-success bg-opacity-10 text-success mx-auto mb-3">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 class="fw-bold text-dark">Ujian Selesai!</h3>
            <p class="text-secondary">Anda telah menyelesaikan <span class="fw-semibold text-primary">{{ ujian.mapel }}</span> ({{ ujian.judul }}).</p>
        </div>

        <div v-if="ujian.tampilkan_nilai_akhir && hasil" class="view-score fade-in">
            <h6 class="text-muted fw-bold mb-3 text-uppercase" style="letter-spacing: 1px;">Hasil Ujian Anda</h6>
            
            <div class="score-display bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-4 p-4 mb-4">
                <h4 class="fw-bold text-primary mb-0">Menjawab benar {{ hasil.benar }} dari {{ totalSoal }} soal</h4>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <h4 class="text-success mb-0 fw-bold">{{ hasil.benar }}</h4>
                        <small class="text-muted">Benar</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <h4 class="text-danger mb-0 fw-bold">{{ hasil.salah }}</h4>
                        <small class="text-muted">Salah</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <h4 class="text-warning mb-0 fw-bold">{{ hasil.kosong }}</h4>
                        <small class="text-muted">Kosong</small>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="alert alert-info border-0 bg-info bg-opacity-10 mb-4 rounded-3 p-4 fade-in">
            <i class="fa-solid fa-circle-info fs-3 text-info mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">Nilai Disembunyikan</h6>
            <p class="text-secondary small mb-0">Nilai ujian tidak ditampilkan secara otomatis sesuai dengan pengaturan guru/admin.</p>
        </div>

        <button class="btn btn-outline-secondary btn-lg fw-semibold px-5 rounded-pill" @click="kembaliKeDashboard">
            <i class="fa-solid fa-house me-2"></i> Kembali ke Dashboard Utama
        </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const route = useRoute();

const ujian = ref({ mapel: '', judul: '', tampilkan_nilai_akhir: false });
const hasil = ref(null);
const isLoading = ref(true);

const totalSoal = computed(() => {
    if (!hasil.value) return 0;
    return (hasil.value.benar || 0) + (hasil.value.salah || 0) + (hasil.value.kosong || 0);
});

onMounted(async () => {
    const sessionHash = route.query.session;
    if (!sessionHash) {
        router.push('/vue/dashboard/siswa');
        return;
    }
    
    try {
        const response = await axios.get(`/ujian/sesi/${sessionHash}/hasil`, {
            headers: { 'Accept': 'application/json' }
        });
        if (response.data.success && response.data.data) {
            const resData = response.data.data;
            const session = resData.session || {};
            const stats = resData.stats || {};
            ujian.value = {
                mapel: session.mapel || '-',
                judul: session.judul || '-',
                tampilkan_nilai_akhir: !!resData.can_show
            };
            hasil.value = {
                nilai_akhir: session.nilai_akhir ?? 0,
                benar: stats.benar ?? 0,
                salah: stats.salah ?? 0,
                kosong: stats.kosong ?? 0
            };
        }
    } catch (e) {
        console.error("Gagal memuat hasil ujian", e);
    } finally {
        isLoading.value = false;
    }
});

const kembaliKeDashboard = () => {
    router.push('/vue/dashboard/siswa');
};
</script>

<style scoped>
.finish-wrapper {
    background-color: #f1f5f9;
    min-height: 100vh;
}
.finish-card {
    border-radius: 20px;
}
.icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
}
.fade-in {
    animation: fadeIn 0.8s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
