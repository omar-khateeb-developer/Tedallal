<!DOCTYPE html>
<html lang="ar" dir="rtl" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title id="page-title">403 · صالة خاصة ومحمية | تدلّل Tedallal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#060913">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        :root {
            --gold-light: #FFF5D0;
            --gold-mid: #E5C37A;
            --gold-base: #C59B50;
            --gold-dark: #8F6920;
            --amber-deep: #1e1305;
            --amber-glow: rgba(217, 119, 6, 0.35);
            --purple-glow: rgba(124, 58, 237, 0.28);
            --gold-glow: rgba(212, 175, 55, 0.38);
        }

        html {
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 50% -10%, #1a153b 0%, #0c091f 45%, #05040a 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Tajawal', 'Inter', system-ui, -apple-system, sans-serif;
            color: #f8fafc;
            position: relative;
            overflow-x: hidden;
            padding: 5rem 1rem 2rem;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Ambient Lighting */
        .ambient-orb {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            filter: blur(100px);
        }

        .ambient-1 {
            top: -120px;
            right: -80px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.3) 0%, transparent 70%);
            animation: pulseOrb 9s ease-in-out infinite alternate;
        }

        .ambient-2 {
            bottom: -140px;
            left: -100px;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.25) 0%, transparent 70%);
            animation: pulseOrb 11s ease-in-out infinite alternate-reverse;
        }

        .ambient-3 {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.12) 0%, transparent 70%);
        }

        @keyframes pulseOrb {
            0% { transform: scale(1) translateY(0); opacity: 0.25; }
            100% { transform: scale(1.15) translateY(-25px); opacity: 0.45; }
        }

        /* Subtle grid background */
        .grid-overlay {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 44px 44px;
            pointer-events: none;
            z-index: 0;
            opacity: 0.45;
        }

        /* Top Fixed Brand Bar */
        .top-nav {
            position: fixed;
            top: 1.25rem;
            left: 1.5rem;
            right: 1.5rem;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1100px;
            margin: 0 auto;
            pointer-events: none;
        }

        .brand-pill, .lang-toggle {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.25);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: #fef08a;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
        }

        .lang-toggle {
            border-color: rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
            font-size: 0.775rem;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .brand-pill:hover, .lang-toggle:hover {
            background: rgba(255, 255, 255, 0.09);
            transform: translateY(-1px);
            border-color: rgba(212, 175, 55, 0.5);
        }

        /* Main Card Container */
        .card-container {
            position: relative;
            z-index: 10;
            max-width: 640px;
            width: 100%;
            margin: auto;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.015) 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 
                0 30px 80px -20px rgba(0, 0, 0, 0.7),
                0 0 50px -10px rgba(217, 119, 6, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                inset 0 0 20px rgba(212, 175, 55, 0.05);
            border-radius: 32px;
            padding: 3rem 2.25rem 2.5rem;
            text-align: center;
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            overflow: hidden;
            animation: cardAppear 0.9s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardAppear {
            0% { opacity: 0; transform: translateY(28px) scale(0.97); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Shield & Lock Emblem */
        .shield-emblem {
            width: 82px;
            height: 82px;
            margin: 0 auto 1.25rem;
            position: relative;
            filter: drop-shadow(0 8px 18px rgba(212, 175, 55, 0.4));
            animation: emblemPulse 4s ease-in-out infinite alternate;
        }

        @keyframes emblemPulse {
            0% { transform: translateY(0) scale(1); filter: drop-shadow(0 6px 14px rgba(212, 175, 55, 0.3)); }
            100% { transform: translateY(-5px) scale(1.04); filter: drop-shadow(0 12px 28px rgba(217, 119, 6, 0.6)); }
        }

        /* 403 Large Display */
        .num-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 0.95;
            margin-bottom: 0.75rem;
        }

        .num-403 {
            font-family: 'Cinzel', 'Tajawal', serif;
            font-size: clamp(5.2rem, 14vw, 8.5rem);
            font-weight: 800;
            letter-spacing: 0.04em;
            background: linear-gradient(135deg, #FFF8DC 0%, #F5D77F 25%, #D4AF37 50%, #AA8022 80%, #68490E 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        /* Error Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1.15rem;
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 9999px;
            color: #fde047;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 1.25rem;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.12);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #eab308;
            box-shadow: 0 0 10px #eab308;
            animation: dotBlink 1.8s ease-in-out infinite;
        }

        @keyframes dotBlink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.85); }
        }

        /* Typography */
        .headline {
            font-size: clamp(1.4rem, 3.2vw, 1.95rem);
            font-weight: 800;
            color: #ffffff;
            line-height: 1.35;
            margin-bottom: 0.85rem;
            letter-spacing: -0.01em;
        }

        .description {
            font-size: clamp(0.95rem, 2vw, 1.05rem);
            color: #94a3b8;
            line-height: 1.65;
            max-width: 520px;
            margin: 0 auto 1.5rem;
            font-weight: 500;
        }

        .notice-box {
            background: rgba(217, 119, 6, 0.08);
            border: 1px solid rgba(217, 119, 6, 0.25);
            border-radius: 16px;
            padding: 0.75rem 1rem;
            margin: 0 auto 1.5rem;
            max-width: 500px;
            color: #fde68a;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 600px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.9rem 1.25rem;
            border-radius: 16px;
            font-size: 0.925rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            outline: none;
        }

        .btn-gold {
            background: linear-gradient(135deg, #f5d061 0%, #e5b842 50%, #c4942b 100%);
            color: #0b0914;
            box-shadow: 0 6px 22px rgba(212, 175, 55, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .btn-gold:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(212, 175, 55, 0.5);
        }

        .btn-indigo {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(129, 140, 248, 0.35);
            color: #c7d2fe;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.15);
        }

        .btn-indigo:hover {
            background: rgba(99, 102, 241, 0.25);
            border-color: rgba(129, 140, 248, 0.6);
            transform: translateY(-2px);
            color: #ffffff;
        }

        .btn-glass {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* Concierge Callout */
        .concierge-card {
            background: rgba(16, 185, 129, 0.07);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 20px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            text-align: right;
            transition: background 0.25s ease;
        }

        [dir="ltr"] .concierge-card {
            text-align: left;
        }

        @media (max-width: 580px) {
            .concierge-card {
                flex-direction: column;
                text-align: center;
            }
            [dir="ltr"] .concierge-card {
                text-align: center;
            }
        }

        .concierge-title {
            color: #34d399;
            font-size: 0.88rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.2rem;
        }

        .concierge-desc {
            color: #94a3b8;
            font-size: 0.775rem;
            line-height: 1.45;
        }

        .btn-whatsapp {
            background: #10b981;
            color: #022c22;
            padding: 0.65rem 1.15rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .btn-whatsapp:hover {
            background: #34d399;
            transform: scale(1.04);
        }

        /* Footer */
        .footer-text {
            margin-top: 1.75rem;
            font-size: 0.775rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <!-- Background Decor -->
    <div class="grid-overlay"></div>
    <div class="ambient-orb ambient-1"></div>
    <div class="ambient-orb ambient-2"></div>
    <div class="ambient-orb ambient-3"></div>

    <!-- Top Navigation -->
    <header class="top-nav">
        <a href="/" class="brand-pill">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
            </svg>
            <span id="brand-text">تدلّل · Tedallal</span>
        </a>

        <button onclick="toggleLang()" class="lang-toggle" id="lang-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
                <path d="M2 12h20"/>
            </svg>
            <span id="lang-text">English</span>
        </button>
    </header>

    <!-- Main Card -->
    <main class="card-container">
        <!-- Shield & Lock Emblem -->
        <div class="shield-emblem">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%;">
                <defs>
                    <linearGradient id="shield-gold-blade" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFF8DC" />
                        <stop offset="30%" stop-color="#F5D77F" />
                        <stop offset="70%" stop-color="#D4AF37" />
                        <stop offset="100%" stop-color="#8F6920" />
                    </linearGradient>
                </defs>
                <path d="M12 2L3 6V11.5C3 16.5 6.8 21.2 12 22.5C17.2 21.2 21 16.5 21 11.5V6L12 2Z" stroke="url(#shield-gold-blade)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="8.5" y="11.5" width="7" height="5.5" rx="1.5" fill="url(#shield-gold-blade)"/>
                <path d="M10 11.5V9.5C10 8.4 10.9 7.5 12 7.5C13.1 7.5 14 8.4 14 9.5V11.5" stroke="url(#shield-gold-blade)" stroke-width="1.6" stroke-linecap="round"/>
                <circle cx="12" cy="13.8" r="0.8" fill="#1e1809" />
                <path d="M12 14.5V15.7" stroke="#1e1809" stroke-width="0.8" stroke-linecap="round" />
            </svg>
        </div>

        <!-- 403 Numbers -->
        <div class="num-wrap">
            <span class="num-403">403</span>
        </div>

        <!-- Badge -->
        <div>
            <span class="status-badge">
                <span class="pulse-dot"></span>
                <span id="badge-text">✦ رمز الخطأ 403 · صالة خاصة ومحمية ✦</span>
            </span>
        </div>

        <!-- Headline & Description -->
        <h1 class="headline" id="headline-text">عذراً، هذه الصالة مخصصة لأعضاء الإدارة أو رتب محددة</h1>
        <p class="description" id="desc-text">
            أهلاً بك في تدلّل! يبدو أن الصفحة أو الإجراء الذي تحاول فتحه يتطلب صلاحيات مخصصة (مثل صلاحية مدير المطعم، شيف المطبخ، أو الكاشير). لا تقلق، يمكنك العودة لمساحتك المعتادة أو تسجيل الدخول بالحساب المصرح له.
        </p>

        @if(!empty($message))
        <div class="notice-box">
            <span>🔒</span>
            <span>{{ $message }}</span>
        </div>
        @endif

        <!-- Actions -->
        <div class="actions-grid">
            <a href="/dashboard" class="btn btn-gold" id="btn-dash">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span id="btn-dash-text">الذهاب للوحة التحكم</span>
            </a>

            <a href="/login" class="btn btn-indigo" id="btn-login">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="8" cy="15" r="4"/>
                    <path d="m11 12 7-7 3 3-2 2-2-2-3 3"/>
                </svg>
                <span id="btn-login-text">تبديل الحساب / تسجيل الدخول</span>
            </a>

            <a href="/" class="btn btn-glass" id="btn-home">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m10 15 5-3-5-3v6z"/>
                </svg>
                <span id="btn-home-text">العودة للرئيسية</span>
            </a>

            <button type="button" onclick="history.length > 1 ? history.back() : window.location.href='/'" class="btn btn-glass" id="btn-back">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span id="btn-back-text">الرجوع خطوة للخلف</span>
            </button>
        </div>

        <!-- Concierge WhatsApp -->
        <div class="concierge-card">
            <div>
                <div class="concierge-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
                    </svg>
                    <span id="concierge-title-text">كونسيرج تدلّل · طلب ترقية الصلاحية</span>
                </div>
                <div class="concierge-desc" id="concierge-desc-text">
                    إذا كنت تعتقد أنك تستحق الوصول لهذه الصفحة، يمكنك مراسلة إدارة المنصة أو مسؤول المطعم فوراً.
                </div>
            </div>
            <a id="wa-link" href="https://wa.me/963947064593?text=مرحباً%20إدارة%20تدلّل،%20أحاول%20الوصول%20للصفحة%20وظهرت%20لي%20رسالة%20عدم%20وجود%20صلاحية%20(403)" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                <span id="wa-btn-text">محادثة واتساب</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- Footer -->
        <p class="footer-text" id="footer-text">
            منظومة تدلّل السحابية © 2026 · الفخامة والأمان في كل تفصيل
        </p>
    </main>

    <script>
        const i18n = {
            ar: {
                title: '403 · صالة خاصة ومحمية | تدلّل Tedallal',
                badge: '✦ رمز الخطأ 403 · صالة خاصة ومحمية ✦',
                headline: 'عذراً، هذه الصالة مخصصة لأعضاء الإدارة أو رتب محددة',
                desc: 'أهلاً بك في تدلّل! يبدو أن الصفحة أو الإجراء الذي تحاول فتحه يتطلب صلاحيات مخصصة (مثل صلاحية مدير المطعم، شيف المطبخ، أو الكاشير). لا تقلق، يمكنك العودة لمساحتك المعتادة أو تسجيل الدخول بالحساب المصرح له.',
                btnDash: 'الذهاب للوحة التحكم',
                btnLogin: 'تبديل الحساب / تسجيل الدخول',
                btnHome: 'العودة للرئيسية',
                btnBack: 'الرجوع خطوة للخلف',
                conciergeTitle: 'كونسيرج تدلّل · طلب ترقية الصلاحية',
                conciergeDesc: 'إذا كنت تعتقد أنك تستحق الوصول لهذه الصفحة، يمكنك مراسلة إدارة المنصة أو مسؤول المطعم فوراً.',
                waBtn: 'محادثة واتساب',
                footer: 'منظومة تدلّل السحابية © 2026 · الفخامة والأمان في كل تفصيل',
                langLabel: 'English',
                dir: 'rtl'
            },
            en: {
                title: '403 · Exclusive Area | Tedallal',
                badge: '✦ ERROR 403 · RESTRICTED ACCESS ✦',
                headline: 'Exclusive Lounge · Access Restricted to Designated Roles',
                desc: 'Welcome to Tedallal! The page or action you are attempting to open requires specific permissions (such as Restaurant Manager, Kitchen Chef, or Cashier). Rest assured, you can easily return to your authorized workspace or sign in with the proper account.',
                btnDash: 'Go to Dashboard',
                btnLogin: 'Switch Account / Log In',
                btnHome: 'Return to Homepage',
                btnBack: 'Go Back One Step',
                conciergeTitle: 'Tedallal Concierge · Request Access',
                conciergeDesc: 'If you believe you should have access to this section, contact platform management or your restaurant administrator.',
                waBtn: 'WhatsApp Support',
                footer: 'Tedallal Cloud Platform © 2026 · Luxury & Security in Every Detail',
                langLabel: 'العربية',
                dir: 'ltr'
            }
        };

        let currentLang = 'ar';

        function toggleLang() {
            currentLang = currentLang === 'ar' ? 'en' : 'ar';
            applyLang(currentLang);
        }

        function applyLang(lang) {
            const data = i18n[lang];
            document.getElementById('html-root').setAttribute('dir', data.dir);
            document.getElementById('html-root').setAttribute('lang', lang);
            document.getElementById('page-title').innerText = data.title;
            document.getElementById('badge-text').innerText = data.badge;
            document.getElementById('headline-text').innerText = data.headline;
            document.getElementById('desc-text').innerText = data.desc;
            document.getElementById('btn-dash-text').innerText = data.btnDash;
            document.getElementById('btn-login-text').innerText = data.btnLogin;
            document.getElementById('btn-home-text').innerText = data.btnHome;
            document.getElementById('btn-back-text').innerText = data.btnBack;
            document.getElementById('concierge-title-text').innerText = data.conciergeTitle;
            document.getElementById('concierge-desc-text').innerText = data.conciergeDesc;
            document.getElementById('wa-btn-text').innerText = data.waBtn;
            document.getElementById('footer-text').innerText = data.footer;
            document.getElementById('lang-text').innerText = data.langLabel;
        }

        // Auto update current URL in WhatsApp button
        try {
            const current = window.location.href;
            const wa = document.getElementById('wa-link');
            if (wa && current) {
                wa.href = 'https://wa.me/963947064593?text=' + encodeURIComponent('مرحباً إدارة تدلّل، أحاول الوصول للصفحة التالية وظهرت لي رسالة عدم وجود صلاحية (403):\n' + current);
            }
        } catch(e) {}
    </script>
</body>
</html>
