<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CBT SMKN 1 Blora - Vue SPA</title>
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/main.js'])
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
</head>
<body class="bg-gray-100">
    <div id="app">
        <div style="min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Arial,sans-serif;padding:24px;">
            <div style="max-width:520px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;text-align:center;box-shadow:0 12px 30px rgba(15,23,42,.08);">
                <div style="font-weight:800;color:#1e3a8a;font-size:18px;margin-bottom:8px;">Memuat aplikasi CBT...</div>
                <div style="color:#64748b;font-size:14px;line-height:1.5;">Jika tampilan berhenti di sini, bersihkan cache aplikasi lalu buka kembali halaman.</div>
                <a href="/recover-login.html" style="display:inline-block;margin-top:16px;border-radius:8px;background:#1d4ed8;color:#fff;padding:10px 14px;text-decoration:none;font-weight:700;font-size:13px;">Bersihkan Cache</a>
            </div>
        </div>
    </div>
</body>
</html>
