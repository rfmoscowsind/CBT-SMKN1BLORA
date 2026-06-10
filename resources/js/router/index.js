import { createRouter, createWebHistory } from 'vue-router';

// Lazy load Dashboards
const SuperAdminDashboard = () => import('../pages/Dashboard/SuperAdminDashboard.vue');
const PanitiaDashboard = () => import('../pages/Dashboard/PanitiaDashboard.vue');
const GuruDashboard = () => import('../pages/Dashboard/GuruDashboard.vue');
const PengawasDashboard = () => import('../pages/Dashboard/PengawasDashboard.vue');
const SiswaDashboard = () => import('../pages/Dashboard/SiswaDashboard.vue');

// Lazy load Exam interfaces
const ExamInterface = () => import('../pages/Exam/ExamInterface.vue');
const ExamToken = () => import('../pages/Exam/ExamToken.vue');
const ExamFinish = () => import('../pages/Exam/ExamFinish.vue');

// Lazy load Management Pages
const StaffManagement = () => import('../pages/Management/StaffManagement.vue');
const StudentManagement = () => import('../pages/Management/StudentManagement.vue');
const MasterDataManagement = () => import('../pages/Management/MasterDataManagement.vue');
const QuestionBank = () => import('../pages/Management/QuestionBank.vue');
const ExamSchedule = () => import('../pages/Management/ExamSchedule.vue');
const ExamResults = () => import('../pages/Management/ExamResults.vue');
const ExamResultArchives = () => import('../pages/Management/ExamResultArchives.vue');
const ExamResultDownloads = () => import('../pages/Management/ExamResultDownloads.vue');
const DeviceFingerprints = () => import('../pages/Management/DeviceFingerprints.vue');
const LiveRadar = () => import('../pages/Monitoring/LiveRadar.vue');

const routes = [
    // ── Old URL aliases (backward compat) ─────────────────────────
    { path: '/kelola', name: 'Kelola', component: MasterDataManagement },
    { path: '/kelola/master', component: MasterDataManagement },
    { path: '/kelola/users', component: StaffManagement },
    { path: '/kelola/questions', component: QuestionBank },
    { path: '/kelola/schedules', component: ExamSchedule },

    // ── Dashboards ────────────────────────────────────────────────
    { path: '/vue/dashboard/superadmin', name: 'SuperAdminDashboard', component: SuperAdminDashboard },
    { path: '/vue/dashboard/panitia', name: 'PanitiaDashboard', component: PanitiaDashboard },
    { path: '/vue/dashboard/guru', name: 'GuruDashboard', component: GuruDashboard },
    { path: '/vue/dashboard/pengawas', name: 'PengawasDashboard', component: PengawasDashboard },
    { path: '/vue/dashboard/siswa', name: 'SiswaDashboard', component: SiswaDashboard },
    
    // ── Monitoring Routes ─────────────────────────────────────────
    { path: '/vue/monitoring/radar', name: 'LiveRadar', component: LiveRadar },
    
    // ── Management Routes ─────────────────────────────────────────
    { path: '/vue/management/staff', name: 'StaffManagement', component: StaffManagement },
    { path: '/vue/management/siswa', name: 'StudentManagement', component: StudentManagement },
    { path: '/vue/management/master', name: 'MasterDataManagement', component: MasterDataManagement },
    { path: '/vue/management/soal', name: 'QuestionBank', component: QuestionBank },
    { path: '/vue/management/jadwal', name: 'ExamSchedule', component: ExamSchedule },
    { path: '/vue/management/hasil', name: 'ExamResults', component: ExamResults },
    { path: '/vue/management/arsip-hasil', name: 'ExamResultArchives', component: ExamResultArchives },
    { path: '/vue/management/download-hasil', name: 'ExamResultDownloads', component: ExamResultDownloads },
    { path: '/vue/management/fingerprints', name: 'DeviceFingerprints', component: DeviceFingerprints },

    // ── Exam ──────────────────────────────────────────────────────
    { path: '/vue/ujian/token', name: 'ExamToken', component: ExamToken },
    { path: '/vue/ujian', name: 'ExamInterface', component: ExamInterface },
    { path: '/vue/ujian/selesai', name: 'ExamFinish', component: ExamFinish },
    
    // ── Fallback ──────────────────────────────────────────────────
    { 
        path: '/:pathMatch(.*)*', 
        name: 'NotFound',
        beforeEnter(to, from, next) {
            // Memaksa browser melakukan hard-reload ke endpoint Laravel
            // supaya Laravel bisa mengecek role user dan me-redirect dengan benar
            window.location.href = '/dashboard';
        }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

export default router;
