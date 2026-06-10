export function generateDeviceFingerprint() {
    const localAnchor = getLocalStorageAnchor();
    const webgl = getWebGLFingerprint();
    const parsedUa = parseUserAgent(navigator.userAgent);
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;

    const components = {
        userAgent: navigator.userAgent,
        browserName: parsedUa.browserName,
        browserVersion: parsedUa.browserVersion,
        osName: parsedUa.osName,
        osVersion: parsedUa.osVersion,
        deviceType: parsedUa.deviceType,
        platform: navigator.platform || 'unknown',
        vendor: navigator.vendor || 'unknown',
        userAgentBrands: navigator.userAgentData?.brands || [],
        mobileHint: navigator.userAgentData?.mobile ?? null,
        language: navigator.language || navigator.userLanguage,
        languages: navigator.languages || [],
        screenRes: `${window.screen.width}x${window.screen.height}`,
        availableScreenRes: `${window.screen.availWidth}x${window.screen.availHeight}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        devicePixelRatio: window.devicePixelRatio || 1,
        colorDepth: window.screen.colorDepth,
        pixelDepth: window.screen.pixelDepth,
        orientation: screen.orientation?.type || window.orientation || 'unknown',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
        deviceMemory: navigator.deviceMemory || 'unknown',
        maxTouchPoints: navigator.maxTouchPoints || 0,
        touchSupport: ('ontouchstart' in window) || (navigator.maxTouchPoints || 0) > 0,
        cookiesEnabled: navigator.cookieEnabled,
        doNotTrack: navigator.doNotTrack || window.doNotTrack || 'unspecified',
        online: navigator.onLine,
        connectionType: connection?.effectiveType || connection?.type || 'unknown',
        connectionDownlink: connection?.downlink || 'unknown',
        connectionRtt: connection?.rtt || 'unknown',
        localStorageAnchor: localAnchor.id,
        localStorageAnchorSource: localAnchor.source,
        localStorageAnchorAvailable: localAnchor.available,
        hasDeviceMotion: typeof DeviceMotionEvent !== 'undefined',
        hasDeviceOrientation: typeof DeviceOrientationEvent !== 'undefined',
        hasVibration: typeof navigator.vibrate === 'function',
        pointerCoarse: window.matchMedia?.('(pointer: coarse)').matches ?? null,
        hoverNone: window.matchMedia?.('(hover: none)').matches ?? null,
        standaloneMode: window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone || false,
        webglVendor: webgl.vendor,
        webglRenderer: webgl.renderer,
        webglHash: webgl.hash,
        canvasHash: getCanvasFingerprint(),
        collectedAt: new Date().toISOString()
    };
    const risk = analyzeEnvironment(components);
    components.riskScore = risk.score;
    components.riskLevel = risk.level;
    components.riskFlags = risk.flags;
    components.cloudPhoneSuspected = risk.cloudPhoneSuspected;
    components.cloudPhoneSignals = risk.cloudPhoneSignals;

    const stableComponents = {
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
        canvasHash: components.canvasHash
    };

    const legacyComponents = { ...stableComponents };
    delete legacyComponents.localStorageAnchor;
    components.legacyHash = hashString(JSON.stringify(legacyComponents), 'dfp_');
    
    return {
        hash: hashString(JSON.stringify(stableComponents), 'dfp_'),
        components
    };
}

function getLocalStorageAnchor() {
    const key = 'cbt_device_anchor_v1';
    const serverAnchor = getServerIssuedAnchor();

    try {
        const current = localStorage.getItem(key);
        if (current && isValidAnchor(current)) {
            clearServerIssuedAnchorParam();
            return { id: current, source: 'localStorage', available: true };
        }

        const next = isValidAnchor(serverAnchor) ? serverAnchor : createAnchor();
        localStorage.setItem(key, next);
        clearServerIssuedAnchorParam();

        return {
            id: next,
            source: isValidAnchor(serverAnchor) ? 'server-issued' : 'browser-generated',
            available: true
        };
    } catch (e) {
        const fallback = isValidAnchor(serverAnchor) ? serverAnchor : createAnchor();
        clearServerIssuedAnchorParam();
        return { id: fallback, source: 'memory-fallback', available: false };
    }
}

function getServerIssuedAnchor() {
    try {
        return new URLSearchParams(window.location.search).get('device_anchor') || '';
    } catch (e) {
        return '';
    }
}

function clearServerIssuedAnchorParam() {
    try {
        const url = new URL(window.location.href);
        if (!url.searchParams.has('device_anchor')) return;
        url.searchParams.delete('device_anchor');
        window.history.replaceState({}, document.title, `${url.pathname}${url.search}${url.hash}`);
    } catch (e) {
        // Ignore history/local URL restrictions.
    }
}

function createAnchor() {
    if (crypto?.randomUUID) return crypto.randomUUID();

    const bytes = new Uint8Array(16);
    crypto?.getRandomValues?.(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function isValidAnchor(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
}

function analyzeEnvironment(c) {
    const flags = [];
    let score = 0;
    const add = (weight, code, message, cloudSignal = false) => {
        score += weight;
        flags.push({ code, weight, message, cloudSignal });
    };

    const ua = String(c.userAgent || '').toLowerCase();
    const renderer = `${c.webglVendor || ''} ${c.webglRenderer || ''}`.toLowerCase();
    const isMobileUa = /android|iphone|ipad|ipod|mobile/.test(ua);
    const isAndroid = /android/.test(ua);
    const isLikelyPhone = c.deviceType === 'HP' || isMobileUa || c.mobileHint === true;
    const virtualRenderer = /(swiftshader|llvmpipe|virtualbox|vmware|parallels|virgl|qemu|emulator|android emulator|software rasterizer|mesa offscreen|google swiftshader)/i;

    if (virtualRenderer.test(renderer)) {
        add(45, 'virtual_webgl', 'WebGL renderer terlihat virtual/software renderer.', true);
    }

    if ((c.webglRenderer === 'unsupported' || c.webglRenderer === 'blocked') && isLikelyPhone) {
        add(18, 'webgl_missing_mobile', 'WebGL tidak tersedia/terblokir pada perangkat yang mengaku mobile.', true);
    }

    if (isLikelyPhone && (!c.touchSupport || Number(c.maxTouchPoints || 0) === 0)) {
        add(35, 'mobile_without_touch', 'User agent mobile/HP tetapi touch support tidak terdeteksi.', true);
    }

    if (isLikelyPhone && c.pointerCoarse === false) {
        add(18, 'mobile_without_coarse_pointer', 'Perangkat mobile tetapi pointer kasar/touch utama tidak terdeteksi.', true);
    }

    if (isLikelyPhone && c.hoverNone === false) {
        add(10, 'mobile_with_hover', 'Perangkat mobile terdeteksi punya hover seperti desktop.', true);
    }

    if (isAndroid && c.mobileHint === false) {
        add(20, 'android_mobile_hint_false', 'User agent Android tetapi browser mobile hint bernilai false.', true);
    }

    if (isLikelyPhone && Number(c.devicePixelRatio || 0) <= 1) {
        add(12, 'low_mobile_dpr', 'Pixel ratio rendah untuk perangkat HP modern.', false);
    }

    if (isLikelyPhone && Number(c.hardwareConcurrency || 0) > 16) {
        add(16, 'too_many_mobile_cores', 'Jumlah CPU core tidak lazim untuk HP peserta.', true);
    }

    if (isLikelyPhone && (c.hasDeviceMotion === false || c.hasDeviceOrientation === false)) {
        add(10, 'mobile_sensor_api_missing', 'API motion/orientation tidak tersedia pada perangkat yang mengaku HP.', false);
    }

    if (isLikelyPhone && c.hasVibration === false) {
        add(8, 'mobile_vibration_missing', 'API vibration tidak tersedia pada perangkat yang mengaku HP.', false);
    }

    if (c.timezone && c.timezone !== 'Asia/Jakarta') {
        add(8, 'timezone_outside_jakarta', 'Zona waktu perangkat bukan Asia/Jakarta.', false);
    }

    if (Array.isArray(c.languages) && c.languages.length === 0) {
        add(6, 'languages_empty', 'Daftar bahasa browser kosong.', false);
    }

    if (isLikelyPhone && c.connectionType === 'unknown' && c.connectionDownlink === 'unknown') {
        add(6, 'connection_info_hidden', 'Informasi koneksi tidak tersedia.', false);
    }

    if (/headless|phantomjs|selenium|webdriver/.test(ua)) {
        add(60, 'automation_user_agent', 'User agent mengandung indikator automation/headless.', true);
    }

    const cloudPhoneSignals = flags.filter(flag => flag.cloudSignal).map(flag => flag.code);
    const cappedScore = Math.min(score, 100);

    return {
        score: cappedScore,
        level: cappedScore >= 60 ? 'high' : cappedScore >= 30 ? 'medium' : cappedScore >= 12 ? 'low' : 'normal',
        flags,
        cloudPhoneSuspected: cappedScore >= 45 || cloudPhoneSignals.length >= 2,
        cloudPhoneSignals
    };
}

function parseUserAgent(ua) {
    const browserChecks = [
        ['Edge', /Edg\/([\d.]+)/],
        ['Chrome', /Chrome\/([\d.]+)/],
        ['Firefox', /Firefox\/([\d.]+)/],
        ['Safari', /Version\/([\d.]+).*Safari/],
        ['Samsung Internet', /SamsungBrowser\/([\d.]+)/],
        ['Opera', /OPR\/([\d.]+)/]
    ];

    const osChecks = [
        ['Android', /Android\s([\d.]+)/],
        ['iOS', /(?:iPhone|iPad|iPod).*OS\s([\d_]+)/],
        ['Windows', /Windows NT\s([\d.]+)/],
        ['MacOS', /Mac OS X\s([\d_]+)/],
        ['Linux', /Linux/]
    ];

    const browser = browserChecks.find(([, pattern]) => pattern.test(ua));
    const os = osChecks.find(([, pattern]) => pattern.test(ua));
    const osMatch = os?.[1].exec(ua);
    const browserMatch = browser?.[1].exec(ua);

    let deviceType = 'Desktop';
    if (/iPad|Tablet/i.test(ua)) deviceType = 'Tablet';
    else if (/Mobi|Android|iPhone|iPod/i.test(ua)) deviceType = 'HP';

    return {
        browserName: browser?.[0] || 'Unknown',
        browserVersion: browserMatch?.[1] || 'unknown',
        osName: os?.[0] || 'Unknown',
        osVersion: (osMatch?.[1] || 'unknown').replaceAll('_', '.'),
        deviceType
    };
}

function getWebGLFingerprint() {
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        if (!gl) return { vendor: 'unsupported', renderer: 'unsupported', hash: 'unsupported' };

        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        const vendor = debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR);
        const renderer = debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
        const raw = `${vendor}|${renderer}|${gl.getParameter(gl.VERSION)}|${gl.getParameter(gl.SHADING_LANGUAGE_VERSION)}`;

        return {
            vendor: vendor || 'unknown',
            renderer: renderer || 'unknown',
            hash: hashString(raw, 'wgl_')
        };
    } catch (e) {
        return { vendor: 'blocked', renderer: 'blocked', hash: 'blocked' };
    }
}

function getCanvasFingerprint() {
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) return 'no_ctx';
        ctx.textBaseline = "top";
        ctx.font = "14px 'Arial'";
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#f60";
        ctx.fillRect(125,1,62,20);
        ctx.fillStyle = "#069";
        ctx.fillText("CBT-SMKN1-BLORA", 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText("CBT-SMKN1-BLORA", 4, 17);
        const dataUrl = canvas.toDataURL();
        
        return hashString(dataUrl, 'cFP_');
    } catch (e) {
        return 'unsupported';
    }
}

function hashString(value, prefix = '') {
    let hash = 0;
    for (let i = 0; i < value.length; i++) {
        const char = value.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash = hash & hash;
    }
    return prefix + Math.abs(hash).toString(16);
}
