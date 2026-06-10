<!DOCTYPE html>
<html lang=id>
<head>
    <meta charset=UTF-8>
    <meta name=viewport content=width=device-width, initial-scale=1.0>
    <title>CBT SMKN 1 Blora</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #172554; }
        main { width: min(92%, 760px); padding: 48px; background: #fff; border-radius: 20px; box-shadow: 0 18px 50px rgba(30, 64, 175, .16); }
        .badge { display: inline-block; padding: 7px 12px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: 13px; font-weight: 700; letter-spacing: .04em; }
        h1 { margin: 22px 0 10px; font-size: clamp(30px, 7vw, 52px); color: #1e3a8a; }
        p { margin: 0; color: #475569; font-size: 18px; line-height: 1.6; }
        section { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 32px; }
        article { padding: 16px; border: 1px solid #dbeafe; border-radius: 12px; background: #f8fafc; }
        strong { display: block; margin-bottom: 6px; color: #1e40af; }
        small { color: #64748b; line-height: 1.45; }
        footer { margin-top: 32px; padding-top: 18px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px; }
        @media (max-width: 640px) { main { padding: 28px; } section { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main>
        <span class=badge>LARAVEL 13 · HTML BLADE</span>
        <h1>CBT SMKN 1 Blora</h1>
        <p>Fondasi aplikasi Computer-Based Test telah aktif. Halaman login, dashboard, dan modul ujian akan dibangun bertahap di atas instalasi ini.</p>
        <section>
            <article><strong>Server Web</strong><small>Laravel 13 dan PHP-FPM aktif.</small></article>
            <article><strong>Antarmuka</strong><small>HTML responsif menggunakan Blade.</small></article>
            <article><strong>Status</strong><small>Bootstrap awal berhasil dijalankan.</small></article>
        </section>
        <footer>CBT SMKN 1 Blora · {{ now()->format('Y') }}</footer>
    </main>
</body>
</html>
