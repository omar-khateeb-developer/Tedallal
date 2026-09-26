<!DOCTYPE html>
<html lang="ar" dir="rtl" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title id="page-title">الموقع تحت الصيانة · Tedallal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0b091a 0%, #1e1b4b 50%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Tajawal', 'Inter', system-ui, -apple-system, sans-serif;
            position: relative;
            overflow-x: hidden;
            padding: 1.5rem;
            color: #ffffff;
        }

        .bg-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.2;
            animation: float 8s ease-in-out infinite;
        }
        .circle-1 { width: 450px; height: 450px; background: radial-gradient(circle, #7c3aed, transparent); top: -150px; right: -80px; }
        .circle-2 { width: 400px; height: 400px; background: radial-gradient(circle, #2563eb, transparent); bottom: -120px; left: -80px; animation-delay: 3s; }
        .circle-3 { width: 350px; height: 350px; background: radial-gradient(circle, #f59e0b, transparent); top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: 1.5s; opacity: 0.12; }

        .grid-pattern {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(15px, -15px) scale(1.05); }
        }

        .lang-toggle {
            position: fixed; top: 1.25rem; right: 1.25rem; z-index: 100;
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1.15rem;
            background: rgba(255,255,255,0.08); backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.18); border-radius: 9999px;
            color: rgba(255,255,255,0.92); font-size: 0.875rem; font-family: inherit; font-weight: 600;
            cursor: pointer; transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        [dir="ltr"] .lang-toggle { right: auto; left: 1.25rem; }
        .lang-toggle:hover, .lang-toggle:active {
            background: rgba(255,255,255,0.18);
            border-color: rgba(255,255,255,0.35);
            transform: scale(1.03);
        }

        .content-card {
            position: relative; z-index: 10;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            max-width: 520px; width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 2.5rem 1.75rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .icon-container {
            position: relative; width: 96px; height: 96px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.5rem;
        }
        .icon-pulse {
            position: absolute; inset: 0; border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.3) 0%, rgba(124, 58, 237, 0.1) 70%, transparent 100%);
            animation: pulse 2.5s ease-in-out infinite;
        }
        .icon-ring {
            position: absolute; inset: -8px; border-radius: 50%;
            border: 2px dashed rgba(245, 158, 11, 0.35);
            animation: spin 15s linear infinite;
        }
        @keyframes pulse { 0%, 100% { transform: scale(0.95); opacity: 0.8; } 50% { transform: scale(1.15); opacity: 1; } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .main-icon {
            width: 48px; height: 48px; color: #fbbf24;
            filter: drop-shadow(0 0 16px rgba(245, 158, 11, 0.6));
        }

        .status-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.4rem 1.1rem;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            border-radius: 9999px;
            color: #fca5a5; font-size: 0.8125rem; font-weight: 700;
            letter-spacing: 0.03em;
            margin-bottom: 1.25rem;
        }
        .badge-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
            animation: blink 1.4s ease-in-out infinite;
        }
        @keyframes blink { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(0.75); } }

        .headline {
            font-size: clamp(1.5rem, 5vw, 2rem); font-weight: 800; line-height: 1.3;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #fde68a 60%, #f59e0b 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .subheadline {
            font-size: clamp(0.95rem, 3vw, 1.125rem); font-weight: 600;
            color: #cbd5e1; margin-bottom: 1.25rem;
        }
        .body-text {
            font-size: 0.9375rem; line-height: 1.8; color: #94a3b8;
            max-width: 420px; margin-bottom: 1.5rem;
        }

        .divider {
            display: flex; align-items: center; gap: 0.75rem;
            width: 100%; max-width: 260px; margin-bottom: 1.25rem;
        }
        .divider-line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(245, 158, 11, 0.4), transparent); }
        .divider-dot { width: 5px; height: 5px; border-radius: 50%; background: #f59e0b; opacity: 0.6; }

        .return-msg { font-size: 0.875rem; color: #64748b; margin-bottom: 1.75rem; font-style: italic; }

        .brand {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
        }
        .brand-ar { font-size: 0.9375rem; font-weight: 800; color: #e2e8f0; }
        .brand-dot { color: #f59e0b; font-size: 1.1rem; line-height: 1; }
        .brand-en { font-size: 0.9375rem; font-weight: 700; color: #94a3b8; letter-spacing: 0.04em; }

        @media (max-width: 480px) {
            .content-card { padding: 2rem 1.25rem; border-radius: 22px; }
            .icon-container { width: 80px; height: 80px; margin-bottom: 1.25rem; }
            .main-icon { width: 40px; height: 40px; }
        }
    </style>
</head>
<body>
    <div class="bg-overlay">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        <div class="circle circle-3"></div>
        <div class="grid-pattern"></div>
    </div>

    <!-- زر تبديل اللغة -->
    <button id="lang-btn" class="lang-toggle" onclick="toggleLanguage()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
        </svg>
        <span id="lang-btn-text">English</span>
    </button>

    <main class="content-card">
        <div class="icon-container">
            <div class="icon-pulse"></div>
            <div class="icon-ring"></div>
            <!-- Wrench / Screwdriver Icon -->
            <svg class="main-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
            </svg>
        </div>

        <div class="status-badge">
            <span class="badge-dot"></span>
            <span id="txt-badge">قيد الصيانة والتطوير</span>
        </div>

        <h1 class="headline" id="txt-headline">الموقع تحت الصيانة</h1>
        <h2 class="subheadline" id="txt-subheadline">
            @if(isset($isQrScan) && $isQrScan)
                خدمة الطلب من الطاولة متوقفة مؤقتاً
            @else
                نعمل على تحسين خدماتك لتجربة أفضل
            @endif
        </h2>

        <p class="body-text" id="txt-body">
            @if(isset($isQrScan) && $isQrScan)
                نعتذر منكم، نقوم حالياً بأعمال صيانة وتحديثات على نظام الطلب الإلكتروني. سنعود لخدمتكم قريباً جداً.
            @else
                نقوم حالياً بإجراء تحديثات وتحسينات دورية على المنصة لنقدم لك أفضل تجربة ممكنة. سنعود للعمل قريباً جداً.
            @endif
        </p>

        <div class="divider">
            <span class="divider-line"></span>
            <span class="divider-dot"></span>
            <span class="divider-line"></span>
        </div>

        <p class="return-msg" id="txt-return">شكراً لصبركم وحسن تفهمكم.</p>

        <div class="brand">
            <span class="brand-ar">تدلّل</span>
            <span class="brand-dot">·</span>
            <span class="brand-en">Tedallal</span>
        </div>
    </main>

    <script>
        const isQr = {{ isset($isQrScan) && $isQrScan ? 'true' : 'false' }};
        let currentLang = 'ar';

        const i18n = {
            ar: {
                title: 'الموقع تحت الصيانة · Tedallal',
                badge: 'قيد الصيانة والتطوير',
                headline: 'الموقع تحت الصيانة',
                subheadline: isQr ? 'خدمة الطلب من الطاولة متوقفة مؤقتاً' : 'نعمل على تحسين خدماتك لتجربة أفضل',
                body: isQr
                    ? 'نعتذر منكم، نقوم حالياً بأعمال صيانة وتحديثات على نظام الطلب الإلكتروني. سنعود لخدمتكم قريباً جداً.'
                    : 'نقوم حالياً بإجراء تحديثات وتحسينات دورية على المنصة لنقدم لك أفضل تجربة ممكنة. سنعود للعمل قريباً جداً.',
                returnMsg: 'شكراً لصبركم وحسن تفهمكم.',
                btnText: 'English'
            },
            en: {
                title: 'Site Under Maintenance · Tedallal',
                badge: 'Under Scheduled Maintenance',
                headline: 'Site Under Maintenance',
                subheadline: isQr ? 'Table ordering service is temporarily unavailable' : 'We are improving your experience',
                body: isQr
                    ? 'We apologize, our digital ordering system is currently undergoing maintenance. We will be back to serve you very shortly.'
                    : 'We are currently performing scheduled updates and improvements to provide you with the best experience. We will be back online shortly.',
                returnMsg: 'Thank you for your patience and understanding.',
                btnText: 'العربية'
            }
        };

        function toggleLanguage() {
            currentLang = currentLang === 'ar' ? 'en' : 'ar';
            applyLanguage(currentLang);
        }

        function applyLanguage(lang) {
            const data = i18n[lang];
            const root = document.getElementById('html-root');

            root.setAttribute('lang', lang);
            root.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

            document.getElementById('page-title').textContent = data.title;
            document.getElementById('txt-badge').textContent = data.badge;
            document.getElementById('txt-headline').textContent = data.headline;
            document.getElementById('txt-subheadline').textContent = data.subheadline;
            document.getElementById('txt-body').textContent = data.body;
            document.getElementById('txt-return').textContent = data.returnMsg;
            document.getElementById('lang-btn-text').textContent = data.btnText;
        }

        // كشف لغة الجهاز تلقائياً إذا لم تكن عربية
        (function autoDetectLanguage() {
            try {
                const userLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
                if (userLang && !userLang.startsWith('ar')) {
                    currentLang = 'en';
                    applyLanguage('en');
                }
            } catch (e) {}
        })();
    </script>
</body>
</html>
