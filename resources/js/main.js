import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import axios from 'axios';

// Import Bootstrap CSS
import 'bootstrap/dist/css/bootstrap.min.css';
// Note: Font Awesome is loaded via CDN in the HTML templates, or we can load it here if installed.

axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const app = createApp(App);

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const showFatalError = (error) => {
    console.error(error);

    let overlay = document.getElementById('cbt-fatal-error');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'cbt-fatal-error';
        document.body.appendChild(overlay);
    }

    overlay.innerHTML = `
        <div style="position:fixed;inset:0;z-index:99999;display:grid;place-items:center;background:rgba(248,250,252,.96);font-family:Arial,sans-serif;padding:24px;">
            <div style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;box-shadow:0 12px 30px rgba(15,23,42,.16);">
                <h2 style="margin:0 0 10px;color:#991b1b;font-size:20px;">Halaman gagal dimuat</h2>
                <p style="margin:0 0 16px;color:#475569;line-height:1.5;">Terjadi error pada tampilan aplikasi. Silakan muat ulang halaman.</p>
                <pre style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;color:#334155;font-size:12px;max-height:220px;overflow:auto;">${escapeHtml(error?.message || error)}</pre>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                    <button onclick="window.location.reload()" style="border:0;border-radius:8px;background:#1d4ed8;color:#fff;padding:10px 14px;font-weight:700;cursor:pointer;">Muat Ulang</button>
                    <a href="/recover-login.html" style="border-radius:8px;background:#f1f5f9;color:#334155;padding:10px 14px;text-decoration:none;font-weight:700;">Bersihkan Cache</a>
                </div>
            </div>
        </div>
    `;
};

app.config.errorHandler = showFatalError;

router.onError((error) => {
    const message = String(error?.message || error);
    const chunkFailed = /Failed to fetch dynamically imported module|Importing a module script failed|Loading chunk|dynamically imported module/i.test(message);

    if (chunkFailed && !sessionStorage.getItem('cbt_chunk_reload_once')) {
        sessionStorage.setItem('cbt_chunk_reload_once', '1');
        const url = new URL(window.location.href);
        url.searchParams.set('asset_refresh', Date.now().toString());
        window.location.replace(url.toString());
        return;
    }

    showFatalError(error);
});

app.use(createPinia());
app.use(router);

router.isReady().then(() => {
    sessionStorage.removeItem('cbt_chunk_reload_once');
    app.mount('#app');
});
