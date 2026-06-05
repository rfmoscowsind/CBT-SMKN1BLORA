@extends('layouts.app')
@section('title','Dashboard CBT')
@section('page_heading', auth()->user()->role === 'SuperAdmin' ? 'System Overview' : 'Dashboard '.auth()->user()->role)
@section('content')
@if(auth()->user()->role === 'Siswa')
<div class="row mb-4">
    <div class="col-lg-4 mb-4">
        <div class="card p-0 overflow-hidden h-100">
            <div style="height:80px;background:linear-gradient(135deg,#1e3a8a,#3b82f6)"></div>
            <div class="px-4 pb-4">
                <div style="width:80px;height:80px;margin-top:-40px;background:#fff;border:4px solid #f4f7f6;border-radius:50%;display:grid;place-items:center;font-size:2rem;color:#1e3a8a"><i class="fa-solid fa-user-graduate"></i></div>
                <h5 class="fw-bold mt-3 mb-0">{{ auth()->user()->name }}</h5>
                <p class="text-secondary small">NISN: {{ auth()->user()->username }}</p>
                <hr>
                <div class="d-flex justify-content-between"><span class="text-muted small">Status Server</span><span class="badge bg-success rounded-pill"><i class="fa-solid fa-wifi"></i> Terhubung</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <h5 class="fw-bold mb-3"><i class="fa-regular fa-calendar-check text-primary me-2"></i>Jadwal Ujian</h5>
        <div class="row">
            @forelse($schedules as $schedule)
            <div class="col-md-6 mb-3"><div class="card h-100"><div class="d-flex align-items-center mb-3"><div class="stat-icon"><i class="fa-solid fa-laptop-file"></i></div><div><h6 class="fw-bold mb-1">{{ $schedule->judul }}</h6><small class="text-muted">{{ $schedule->durasi_menit }} menit</small></div></div><div class="small text-muted mb-3"><div><i class="fa-regular fa-clock me-2"></i>{{ $schedule->waktu_mulai }}</div><div><i class="fa-solid fa-flag-checkered me-2"></i>{{ $schedule->waktu_selesai }}</div></div><form method="POST" action="{{ route('exams.start',$encode($schedule->id)) }}">@csrf @if($schedule->gunakan_token)<input name="token" maxlength="12" placeholder="MASUKKAN TOKEN" style="text-align:center;text-transform:uppercase;letter-spacing:2px;font-weight:700">@endif<button class="w-100"><i class="fa-solid fa-play"></i> MULAI UJIAN</button></form></div></div>
            @empty
            <div class="alert alert-info">Belum ada jadwal ujian aktif untuk kelas Anda.</div>
            @endforelse
        </div>
    </div>
</div>
@else
@php
    $isGuru = auth()->user()->role === 'Guru';
    $isPengawas = auth()->user()->role === 'Pengawas';
@endphp
<style>
.dashboard-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.quick-link{display:flex;align-items:center;gap:12px;padding:14px;border:1px solid #e2e8f0;border-radius:10px;color:#334155;background:#fff;transition:.18s}.quick-link:hover{border-color:#93c5fd;background:#eff6ff;color:#1e3a8a}.quick-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:9px;background:#eff6ff;color:#1d4ed8}.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}.table-card{padding:0;overflow:hidden}.table-head{padding:18px 20px;border-bottom:1px solid #e2e8f0}.dashboard-table{margin:0}.dashboard-table th,.dashboard-table td{padding:13px 16px;vertical-align:middle}.empty-box{padding:30px;text-align:center;color:#94a3b8}.report-links a{font-size:.7rem;margin-right:8px;font-weight:700}.soft-number{font-size:1.65rem;font-weight:700;color:#1e293b}@media(max-width:760px){.dashboard-intro{display:block}.quick-grid{grid-template-columns:1fr}}
</style>
<div class="dashboard-intro mb-4">
    <div><h1 class="page-title mb-1">Dashboard {{ auth()->user()->role }}</h1><p class="muted mb-0">Selamat datang, {{ auth()->user()->name }}. Informasi operasional CBT diperbarui dari server.</p></div>
    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2 mt-2"><i class="fa-solid fa-circle me-1" style="font-size:7px"></i> Server Online</span>
</div>
<div class="row g-3 mb-4">
    @if($isGuru)
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-file-circle-check"></i></div><div><div class="stat-label">Bank Soal</div><div class="soft-number">{{ $questionCount }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#22c55e"><div class="stat-icon" style="color:#16a34a;background:#f0fdf4"><i class="fa-solid fa-box-archive"></i></div><div><div class="stat-label">Paket Siap</div><div class="soft-number">{{ $readyPackageCount }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#f59e0b"><div class="stat-icon" style="color:#d97706;background:#fffbeb"><i class="fa-solid fa-pen-to-square"></i></div><div><div class="stat-label">Isian Menunggu Nilai</div><div class="soft-number">{{ $pendingEssayCount }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#a855f7"><div class="stat-icon" style="color:#a855f7;background:#faf5ff"><i class="fa-solid fa-calendar-days"></i></div><div><div class="stat-label">Jadwal Ujian</div><div class="soft-number">{{ $schedules->count() }}</div></div></div></div>
    @else
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div><div><div class="stat-label">Total Siswa</div><div class="soft-number">{{ $studentCount }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#0ea5e9"><div class="stat-icon" style="color:#0284c7;background:#f0f9ff"><i class="fa-solid fa-calendar-day"></i></div><div><div class="stat-label">Jadwal Ujian</div><div class="soft-number">{{ $schedules->count() }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#22c55e"><div class="stat-icon" style="color:#16a34a;background:#f0fdf4"><i class="fa-solid fa-laptop-file"></i></div><div><div class="stat-label">Sesi Aktif</div><div class="soft-number">{{ $activeSessionCount }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card" style="border-left-color:#ef4444"><div class="stat-icon" style="color:#dc2626;background:#fef2f2"><i class="fa-solid fa-wifi"></i></div><div><div class="stat-label">Perlu Diperiksa</div><div class="soft-number">{{ $offlineSessionCount }}</div></div></div></div>
    @endif
</div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-bolt text-warning me-2"></i>Aksi Cepat</h5>
            <p class="small text-muted mb-3">Buka modul sesuai hak akses akun Anda.</p>
            <div class="quick-grid">
                @if($permissions->contains('manage-users'))<a class="quick-link" href="/kelola#manajemen-user"><span class="quick-icon"><i class="fa-solid fa-users"></i></span><span><strong>Data Siswa</strong><small class="d-block text-muted">Akun dan kehadiran</small></span></a>@endif
                @if($permissions->contains('manage-master'))<a class="quick-link" href="/kelola"><span class="quick-icon"><i class="fa-solid fa-school"></i></span><span><strong>Master Sekolah</strong><small class="d-block text-muted">Kelas dan mapel</small></span></a>@endif
                @if($permissions->contains('manage-questions'))<a class="quick-link" href="/kelola"><span class="quick-icon"><i class="fa-solid fa-book-open"></i></span><span><strong>Bank Soal</strong><small class="d-block text-muted">Paket dan soal</small></span></a>@endif
                @if($permissions->contains('manage-schedules'))<a class="quick-link" href="/kelola"><span class="quick-icon"><i class="fa-solid fa-calendar-check"></i></span><span><strong>Jadwal Ujian</strong><small class="d-block text-muted">Master dan sesi</small></span></a>@endif
                @if($permissions->contains('grade-essays'))<a class="quick-link" href="/kelola"><span class="quick-icon"><i class="fa-solid fa-pen-nib"></i></span><span><strong>Nilai Isian</strong><small class="d-block text-muted">{{ $pendingEssayCount }} menunggu</small></span></a>@endif
                @if($permissions->contains('monitor-exams'))<a class="quick-link" href="/kelola#monitoring"><span class="quick-icon"><i class="fa-solid fa-users-viewfinder"></i></span><span><strong>Monitoring</strong><small class="d-block text-muted">Pantau peserta aktif</small></span></a>@endif
                @if($permissions->contains('view-reports'))<a class="quick-link" href="/kelola#laporan"><span class="quick-icon"><i class="fa-solid fa-file-export"></i></span><span><strong>Laporan</strong><small class="d-block text-muted">JSON, Excel, PDF</small></span></a>@endif
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="table-card h-100">
            <div class="table-head"><h5 class="fw-bold mb-1"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Monitoring Peserta</h5><p class="small text-muted mb-0">Ringkasan sesi ujian aktif terbaru.</p></div>
            <div class="table-responsive"><table class="dashboard-table"><tr><th>Peserta</th><th>Status</th><th>Terakhir Terlihat</th></tr>@forelse($activeSessions as $session)<tr><td><strong>{{ $session->name }}</strong><small class="d-block text-muted">{{ $session->username }}</small></td><td>@if($session->online)<span class="text-success"><span class="status-dot bg-success"></span>Online</span>@else<span class="text-danger"><span class="status-dot bg-danger"></span>Perlu dicek</span>@endif</td><td>{{ $session->last_seen_at ?: '-' }}</td></tr>@empty<tr><td colspan="3"><div class="empty-box"><i class="fa-solid fa-circle-check text-success me-1"></i> Belum ada sesi ujian aktif.</div></td></tr>@endforelse</table></div>
        </div>
    </div>
</div>
<div class="table-card mt-4">
    <div class="table-head d-flex justify-content-between align-items-center gap-3"><div><h5 class="fw-bold mb-1"><i class="fa-regular fa-calendar-check text-primary me-2"></i>Jadwal dan Laporan</h5><p class="small text-muted mb-0">Jadwal CBT yang tersedia pada server.</p></div><a class="btn" href="/kelola"><i class="fa-solid fa-gears"></i> Buka Pengelolaan</a></div>
    <div class="table-responsive"><table class="dashboard-table"><tr><th>Ujian</th><th>Mulai</th><th>Selesai</th><th>Durasi</th>@if($permissions->contains('view-reports'))<th>Laporan</th>@endif</tr>@forelse($schedules as $schedule)<tr><td><strong>{{ $schedule->judul }}</strong></td><td>{{ $schedule->waktu_mulai }}</td><td>{{ $schedule->waktu_selesai }}</td><td>{{ $schedule->durasi_menit }} menit</td>@if($permissions->contains('view-reports'))<td class="report-links"><a href="/kelola/laporan/{{ $schedule->id }}/xlsx">Excel</a><a href="/kelola/laporan/{{ $schedule->id }}/pdf">PDF</a></td>@endif</tr>@empty<tr><td colspan="5"><div class="empty-box">Belum ada jadwal ujian.</div></td></tr>@endforelse</table></div>
</div>
@endif
@endsection
