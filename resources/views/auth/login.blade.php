@extends('layouts.app')
@section('standalone', true)
@section('title','Login CBT - SMKN 1 Blora')
@section('content')
<style>
body{background:#f1f5f9}.login-page{min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(circle at top right,#dbeafe 0,#f1f5f9 38%)}.login-card{width:min(430px,100%);padding:0;border-radius:20px;overflow:hidden;box-shadow:0 16px 45px rgba(15,23,42,.1)}.login-accent{height:8px;background:linear-gradient(90deg,#1e3a8a,#3b82f6)}.login-head{text-align:center;padding:28px 28px 14px}.login-logo{width:82px;height:82px;object-fit:contain;margin-bottom:10px}.login-title{font-size:1.15rem;font-weight:800;color:#1e3a8a;letter-spacing:.4px;margin:0}.login-subtitle{font-size:.75rem;color:#64748b;margin:4px 0 0}.login-body{padding:12px 28px 28px}.login-label{display:block;margin:0 0 6px;color:#475569;font-size:.75rem;font-weight:600}.login-field{display:flex;align-items:center;margin-bottom:15px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;overflow:hidden;transition:.2s}.login-field:focus-within{border-color:#1e3a8a;box-shadow:0 0 0 .2rem rgba(30,58,138,.12)}.login-icon{width:44px;display:grid;place-items:center;color:#64748b;background:#f8fafc;align-self:stretch;border-right:1px solid #e2e8f0}.login-field input{margin:0;border:0;border-radius:0;padding:.78rem .8rem;box-shadow:none;outline:0;background:#fff}.login-submit{width:100%;padding:.8rem;margin-top:4px;background:#1e3a8a;font-size:.78rem;letter-spacing:.3px}.login-submit:hover{background:#1d4ed8}.login-help{margin-top:17px;padding:10px;border-radius:8px;background:#f8fafc;color:#94a3b8;text-align:center;font-size:.67rem}.login-footer{text-align:center;color:#94a3b8;font-size:.62rem;margin-top:18px}@media(max-width:480px){.login-body{padding:10px 20px 22px}.login-head{padding-top:22px}}
</style>
<div class="login-page">
    <div class="card login-card">
        <div class="login-accent"></div>
        <div class="login-head">
            <img class="login-logo" src="https://smkn1blora.sch.id/media_library/images/585485ba3fba364ffb5b5ed38d8c4f33.png" alt="Logo SMKN 1 Blora">
            <h1 class="login-title">SMK NEGERI 1 BLORA</h1>
            <p class="login-subtitle">Aplikasi Computer-Based Test (CBT)</p>
        </div>
        <div class="login-body">
            <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
                @csrf
                <input type="hidden" name="device_fp" id="deviceFp">
                <input type="hidden" name="device_raw" id="deviceRaw">
                <label class="login-label" for="username">NIS / NISN / Username</label>
                <div class="login-field">
                    <span class="login-icon"><i class="fa-solid fa-user"></i></span>
                    <input id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan username" required autofocus>
                </div>
                <label class="login-label" for="password">Password</label>
                <div class="login-field">
                    <span class="login-icon"><i class="fa-solid fa-lock"></i></span>
                    <input id="password" name="password" type="password" placeholder="Masukkan password Anda" required>
                </div>
                <button class="login-submit"><i class="fa-solid fa-right-to-bracket"></i> LOGIN / MASUK SISTEM</button>
            </form>
            <div class="login-help"><i class="fa-solid fa-circle-info me-1"></i> Gunakan akun yang diberikan oleh administrator CBT.</div>
            <div class="login-footer"><a href="/recover-login.html">Bersihkan cache jika login kembali ke halaman ini</a></div>
            <div class="login-footer">v1.0.0 &copy; 2026 Tim IT SMKN 1 Blora</div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const anchorKey = 'cbt_device_anchor_v1';

    /**
     * BUG FIX: Original code used `& hash` — since hash starts at 0,
     * `anything & 0 = 0`, so ALL fingerprints were always "dfp_0".
     * Fix: use `| 0` to coerce result to a signed 32-bit integer (djb2 standard).
     */
    function hashString(value, prefix) {
        let hash = 0;
        for (let i = 0; i < value.length; i++) {
            hash = ((hash << 5) - hash + value.charCodeAt(i)) | 0;
        }
        return (prefix || '') + Math.abs(hash).toString(16);
    }

    function createAnchor() {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        const bytes = new Uint8Array(16);
        if (crypto && crypto.getRandomValues) crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes).map(byte => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    function getAnchor() {
        try {
            const current = localStorage.getItem(anchorKey);
            if (current) return { id: current, source: 'localStorage', available: true };
            const next = createAnchor();
            localStorage.setItem(anchorKey, next);
            return { id: next, source: 'login-generated', available: true };
        } catch (e) {
            return { id: createAnchor(), source: 'memory-fallback', available: false };
        }
    }

    function getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            if (!ctx) return 'no_ctx';
            ctx.textBaseline = 'alphabetic';
            ctx.font = "14px 'Arial'";
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('CBT-SMKN1-BLORA', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('CBT-SMKN1-BLORA', 4, 17);
            return hashString(canvas.toDataURL(), 'cFP_');
        } catch (e) {
            return 'unsupported';
        }
    }

    function getWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return { vendor: 'unsupported', renderer: 'unsupported' };
            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            return {
                vendor: debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR),
                renderer: debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER),
            };
        } catch (e) {
            return { vendor: 'blocked', renderer: 'blocked' };
        }
    }

    function generateLoginFingerprint() {
        const anchor = getAnchor();
        const webgl = getWebGLFingerprint();
        const components = {
            localStorageAnchor: anchor.id,
            localStorageAnchorSource: anchor.source,
            localStorageAnchorAvailable: anchor.available,
            userAgent: navigator.userAgent,
            language: navigator.language || navigator.userLanguage,
            screenRes: `${window.screen.width}x${window.screen.height}`,
            colorDepth: window.screen.colorDepth,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
            deviceMemory: navigator.deviceMemory || 'unknown',
            maxTouchPoints: navigator.maxTouchPoints || 0,
            platform: navigator.platform || 'unknown',
            vendor: navigator.vendor || 'unknown',
            webglVendor: webgl.vendor || 'unknown',
            webglRenderer: webgl.renderer || 'unknown',
            canvasHash: getCanvasFingerprint(),
            collectedAt: new Date().toISOString(),
        };
        const stable = {
            localStorageAnchor: components.localStorageAnchor,
            userAgent: components.userAgent,
            language: components.language,
            screenRes: components.screenRes,
            colorDepth: components.colorDepth,
            timezone: components.timezone,
            hardwareConcurrency: components.hardwareConcurrency,
            deviceMemory: components.deviceMemory,
            maxTouchPoints: components.maxTouchPoints,
            platform: components.platform,
            vendor: components.vendor,
            webglVendor: components.webglVendor,
            webglRenderer: components.webglRenderer,
            canvasHash: components.canvasHash,
        };
        // legacyHash: fingerprint WITHOUT localStorageAnchor for backward-compat matching
        const legacy = { ...stable };
        delete legacy.localStorageAnchor;
        components.legacyHash = hashString(JSON.stringify(legacy), 'dfp_');

        return { hash: hashString(JSON.stringify(stable), 'dfp_'), components };
    }

    document.getElementById('loginForm')?.addEventListener('submit', function () {
        const data = generateLoginFingerprint();
        document.getElementById('deviceFp').value = data.hash;
        document.getElementById('deviceRaw').value = JSON.stringify(data.components);
    });
})();
</script>
@endpush
