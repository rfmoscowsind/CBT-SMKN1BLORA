<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CBT SMKN 1 Blora')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--navy:#1e3a8a;--blue:#1d4ed8;--ink:#1e293b;--muted:#64748b;--line:#e2e8f0;--bg:#f1f5f9;--green:#16a34a;--red:#dc2626}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:'Poppins',sans-serif;font-size:14px}a{text-decoration:none}.app-wrapper{display:flex;width:100%;min-height:100vh}.sidebar{width:280px;background:var(--navy);color:#f8fafc;flex-shrink:0;display:flex;flex-direction:column}.sidebar.superadmin{background:#111827}.sidebar-brand{padding:1.45rem;background:rgba(15,23,42,.22);display:flex;align-items:center;border-bottom:1px solid rgba(255,255,255,.12)}.sidebar-brand img{width:42px;margin-right:13px}.sidebar-nav{padding:1rem 0;flex-grow:1}.sidebar-label{padding:0 1.5rem;margin:14px 0 7px;color:#93c5fd;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px}.superadmin .sidebar-label{color:#64748b}.nav-item-custom{padding:.78rem 1.5rem;color:#bfdbfe;display:flex;align-items:center;border-left:4px solid transparent}.superadmin .nav-item-custom{color:#94a3b8}.nav-item-custom:hover,.nav-item-custom.active{color:#fff;background:rgba(255,255,255,.11);border-left-color:#60a5fa}.nav-item-custom i{width:25px}.sidebar-user{padding:1rem;border-top:1px solid rgba(255,255,255,.12)}.main-content{flex-grow:1;min-width:0}.top-navbar{height:70px;padding:0 2rem;background:#fff;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.03);position:sticky;top:0;z-index:1020}.student-navbar{background:var(--navy);box-shadow:0 4px 12px rgba(0,0,0,.1)}.student-navbar img{width:40px}.content-wrap{padding:1.5rem}.card,.table-card{background:#fff;border:0;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.04);padding:1.25rem;margin-bottom:1rem}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.two{grid-template-columns:repeat(2,1fr)}.page-title{font-size:1.45rem;color:var(--ink);font-weight:700}.muted{color:var(--muted)}.stat-card{background:#fff;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.03);padding:1.35rem;display:flex;align-items:center;border-left:5px solid #3b82f6;height:100%}.stat-icon{width:52px;height:52px;border-radius:12px;background:#eff6ff;color:#3b82f6;display:grid;place-items:center;font-size:1.45rem;margin-right:1rem}.stat{font-size:1.7rem;font-weight:700}.stat-label{font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase}.alert{border:0;border-radius:9px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}label{font-size:.78rem;font-weight:500;color:#64748b}input,textarea,select{width:100%;padding:.62rem .72rem;border:1px solid #cbd5e1;border-radius:8px;margin:5px 0 12px;background:#fff;font-family:inherit}input[type=checkbox],input[type=radio]{width:auto}button,.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:8px;padding:.62rem .9rem;background:var(--navy);color:#fff;font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer}.btn:hover,button:hover{background:var(--blue);color:#fff}.danger{background:var(--red)}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.75rem .55rem;border-bottom:1px solid var(--line);font-size:.75rem;vertical-align:top}th{background:#f8fafc;color:#475569;font-weight:600}.logout-form{margin:0}.logout-form button{background:transparent;border:1px solid rgba(255,255,255,.5)}.top-navbar .logout-form button{border-color:#fecaca;color:#dc2626}.top-navbar .logout-form button:hover{background:#fef2f2}.footer-note{text-align:center;color:#94a3b8;font-size:.68rem;margin:22px}.badge-soft{padding:.38rem .65rem;border-radius:20px;background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:700}
        @media(max-width:900px){.sidebar{width:76px}.sidebar-brand{padding:1rem}.sidebar-brand img{width:38px;margin:0}.sidebar-brand div,.sidebar-label,.nav-item-custom span,.sidebar-user div{display:none}.nav-item-custom{padding:1rem 1.45rem}.content-wrap{padding:1rem}.grid,.two{grid-template-columns:1fr}.top-navbar{padding:0 1rem}table{display:block;overflow:auto}}
        @media(max-width:600px){.sidebar{display:none}.student-navbar .container{padding:0 14px}.card{padding:1rem}}
    </style>
    @stack('styles')
</head>
<body>
@php($standalone = View::hasSection('standalone'))
@auth
    @unless($standalone)
        @if(auth()->user()->role === 'Siswa')
            <nav class="navbar navbar-dark student-navbar sticky-top py-3"><div class="container"><a class="navbar-brand d-flex align-items-center gap-2" href="/dashboard"><img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo"><strong>CBT SMKN 1 Blora</strong></a><form class="logout-form" method="POST" action="{{ route('logout') }}">@csrf<button><i class="fa-solid fa-right-from-bracket"></i> Keluar</button></form></div></nav>
        @else
            <div class="app-wrapper"><aside class="sidebar {{ auth()->user()->role === 'SuperAdmin' ? 'superadmin' : '' }}"><div class="sidebar-brand"><img src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo"><div><strong>SMKN 1 BLORA</strong><small class="d-block opacity-75">CBT Enterprise Server</small></div></div><div class="sidebar-nav"><div class="sidebar-label">Menu Utama</div><a class="nav-item-custom {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard"><i class="fa-solid fa-chart-pie"></i><span>Dashboard Overview</span></a><div class="sidebar-label">Operasional CBT</div><a class="nav-item-custom {{ request()->is('kelola*') ? 'active' : '' }}" href="/kelola"><i class="fa-solid fa-gears"></i><span>Pengelolaan CBT</span></a><a class="nav-item-custom" href="/kelola#monitoring"><i class="fa-solid fa-users-viewfinder"></i><span>Monitoring Peserta</span></a><a class="nav-item-custom" href="/kelola#laporan"><i class="fa-solid fa-file-export"></i><span>Laporan Ujian</span></a>@if(auth()->user()->role === 'SuperAdmin')<div class="sidebar-label">SuperAdmin</div><a class="nav-item-custom" href="/kelola#manajemen-user"><i class="fa-solid fa-users-gear"></i><span>Manajemen User</span></a><a class="nav-item-custom" href="/kelola#manajemen-fitur"><i class="fa-solid fa-shield-halved"></i><span>Manajemen Fitur</span></a><a class="nav-item-custom {{ request()->is('vue/management/fingerprints') ? 'active' : '' }}" href="/vue/management/fingerprints"><i class="fa-solid fa-fingerprint"></i><span>Kunci Perangkat</span></a>@endif</div><div class="sidebar-user"><div class="small"><strong>{{ auth()->user()->name }}</strong><span class="d-block opacity-75">{{ auth()->user()->role }}</span></div></div></aside><div class="main-content"><header class="top-navbar"><div><strong>@yield('page_heading', 'CBT SMKN 1 Blora')</strong><span class="badge-soft ms-2"><i class="fa-solid fa-network-wired"></i> LAN Server</span></div><form class="logout-form" method="POST" action="{{ route('logout') }}">@csrf<button><i class="fa-solid fa-power-off"></i> Keluar</button></form></header>
        @endif
    @endunless
@endauth
<main class="{{ $standalone ? '' : (auth()->check() && auth()->user()->role !== 'Siswa' ? 'content-wrap' : 'container py-4') }}">
    @if(session('success'))<div class="alert success"><i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error"><i class="fa-solid fa-triangle-exclamation me-1"></i> {{ $errors->first() }}</div>@endif
    @yield('content')
</main>
@auth @unless($standalone) @if(auth()->user()->role !== 'Siswa')</div></div>@endif @endunless @endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        Promise.all([
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                return Promise.all(registrations.map(function(registration) {
                    return registration.unregister();
                }));
            }),
            window.caches ? caches.keys().then(function(keys) {
                return Promise.all(keys.map(function(key) {
                    return caches.delete(key);
                }));
            }) : Promise.resolve(),
        ]).finally(function () {
            navigator.serviceWorker.register('/sw.js?v=8');
        });
    }
</script>
@stack('scripts')
</body>
</html>
