<template>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo">
            <div>
                <div class="fw-bold fs-6 text-white">SMKN 1 BLORA</div>
                <div style="font-size: 0.7rem; color: #94a3b8;">CBT Enterprise Server</div>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-section-label">Menu Utama</div>
            <router-link to="/vue/dashboard/superadmin" class="nav-item-custom" :class="{ active: currentRoute === 'SuperAdminDashboard' }">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </router-link>
            <router-link v-if="currentUser.role === 'SuperAdmin'" to="/vue/monitoring/radar" class="nav-item-custom" :class="{ active: currentRoute === 'LiveRadar' }">
                <i class="fa-solid fa-tower-broadcast"></i> Radar Real-Time
            </router-link>
            
            <div class="nav-section-label mt-4">Master & Akses</div>
            <router-link to="/vue/management/staff" class="nav-item-custom" :class="{ active: currentRoute === 'StaffManagement' }">
                <i class="fa-solid fa-users-gear"></i> Manajemen Staf
            </router-link>
            <router-link to="/vue/management/master" class="nav-item-custom" :class="{ active: currentRoute === 'MasterDataManagement' }">
                <i class="fa-solid fa-school"></i> Master Sekolah
            </router-link>
            
            <div class="nav-section-label mt-4">Manajemen Ujian</div>
            <router-link to="/vue/management/siswa" class="nav-item-custom" :class="{ active: currentRoute === 'StudentManagement' }">
                <i class="fa-solid fa-users-viewfinder"></i> Manajemen Siswa
            </router-link>
            <router-link to="/vue/management/soal" class="nav-item-custom" :class="{ active: currentRoute === 'QuestionBank' }">
                <i class="fa-solid fa-file-circle-check"></i> Bank Soal
            </router-link>
            <router-link to="/vue/management/jadwal" class="nav-item-custom" :class="{ active: currentRoute === 'ExamSchedule' }">
                <i class="fa-solid fa-calendar-days"></i> Jadwal Ujian
            </router-link>
            <router-link to="/vue/management/hasil" class="nav-item-custom" :class="{ active: currentRoute === 'ExamResults' }">
                <i class="fa-solid fa-square-poll-vertical"></i> Hasil Ujian
            </router-link>
            <router-link to="/vue/management/arsip-hasil" class="nav-item-custom" :class="{ active: currentRoute === 'ExamResultArchives' }">
                <i class="fa-solid fa-box-archive"></i> Arsip Hasil
            </router-link>
            <router-link to="/vue/management/download-hasil" class="nav-item-custom" :class="{ active: currentRoute === 'ExamResultDownloads' }">
                <i class="fa-solid fa-file-pdf"></i> Download Hasil
            </router-link>
            <router-link to="/vue/management/fingerprints" class="nav-item-custom" :class="{ active: currentRoute === 'DeviceFingerprints' }">
                <i class="fa-solid fa-fingerprint"></i> Kunci Perangkat
            </router-link>
        </div>
        
        <div class="p-3 border-top" style="border-color: #1e293b !important;">
            <div class="d-flex align-items-center">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white me-2" style="width: 35px; height: 35px;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="small">
                    <div class="text-white fw-bold">{{ currentUser.name }}</div>
                    <div style="font-size: 0.7rem; color: #94a3b8;">{{ currentUser.role }} Role</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const currentRoute = computed(() => route.name);

const currentUser = ref({ name: 'Loading...', role: 'Admin' });

const fetchUser = async () => {
    try {
        const response = await axios.get('/auth/user', { headers: { 'Accept': 'application/json' } });
        if (response.data) {
            currentUser.value = response.data;
        }
    } catch (e) {
        console.error('Gagal mengambil data user di sidebar', e);
    }
};

onMounted(() => {
    fetchUser();
});
</script>

<style scoped>
.sidebar {
    width: 280px;
    background-color: #111827; 
    color: #f8fafc;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.3s;
    z-index: 1030;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-y: auto;
}
.sidebar-brand {
    padding: 1.5rem;
    background-color: #0f172a;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #1e293b;
}
.sidebar-brand img { width: 40px; margin-right: 15px; }
.sidebar-nav { padding: 1rem 0; flex-grow: 1; }
.nav-section-label {
    padding: 0 1.5rem;
    text-transform: uppercase;
    font-weight: 700;
    font-size: 0.7rem;
    color: #475569;
    margin-bottom: 0.5rem;
}
.nav-item-custom {
    padding: 0.75rem 1.5rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 4px solid transparent;
    font-size: 0.9rem;
}
.nav-item-custom:hover {
    color: #f8fafc;
    background-color: #1e293b;
}
.nav-item-custom.active {
    color: #60a5fa;
    background-color: #1e293b;
    border-left: 4px solid #3b82f6;
    font-weight: 600;
}
.nav-item-custom i { width: 25px; font-size: 1.1rem; }
</style>
