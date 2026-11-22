<!DOCTYPE html>
<html lang="ar" dir="rtl" id="html-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title id="page-title">404 · الطاولة غير موجودة | تدلّل Tedallal</title>
    
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
            --purple-deep: #160e33;
            --purple-glow: rgba(124, 58, 237, 0.35);
            --blue-glow: rgba(37, 99, 235, 0.28);
            --gold-glow: rgba(212, 175, 55, 0.38);
        }

        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 50% -10%, #1a153b 0%, #0c091f 45%, #05040a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Tajawal', 'Inter', system-ui, -apple-system, sans-serif;
            color: #f8fafc;
            position: relative;
            overflow-x: hidden;
            padding: 1.5rem 1rem;
        }

        /* Ambient Lighting & Nebulas */
        .ambient-wrap {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.35;
            will-change: transform;
            animation: floatOrb 12s ease-in-out infinite alternate;
        }

        .orb-1 {
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, #7c3aed 0%, rgba(124, 58, 237, 0) 70%);
            top: -120px;
            right: -100px;
            animation-duration: 14s;
        }

        .orb-2 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, #2563eb 0%, rgba(37, 99, 235, 0) 70%);
            bottom: -150px;
            left: -120px;
            animation-delay: -3s;
            animation-duration: 16s;
        }

        .orb-3 {
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, #d4af37 0%, rgba(212, 175, 55, 0) 70%);
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            animation-duration: 10s;
        }

        .grid-texture {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse at 50% 50%, black 40%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 50%, black 40%, transparent 80%);
        }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(25px, -20px) scale(1.08); }
            100% { transform: translate(-20px, 20px) scale(0.95); }
        }

        /* Top Action Bar (Language Switcher & Brand Tag) */
        .top-bar {
            position: fixed;
            top: 1.25rem;
            left: 1.5rem;
            right: 1.5rem;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            pointer-events: none;
        }

        .brand-badge {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 9999px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            text-decoration: none;
            color: #f1f5f9;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.25s ease;
        }

        .brand-badge:hover {
            border-color: rgba(212, 175, 55, 0.6);
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.18);
        }

        .lang-btn {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 1rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 9999px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: #e2e8f0;
            font-size: 0.85rem;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .lang-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* Main Luxury Card */
        .card-container {
            position: relative;
            z-index: 10;
            max-width: 660px;
            width: 100%;
            margin: auto;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.015) 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 
                0 30px 80px -20px rgba(0, 0, 0, 0.7),
                0 0 50px -10px rgba(124, 58, 237, 0.15),
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
            0% {
                opacity: 0;
                transform: translateY(28px) scale(0.97);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Gold Shimmer Sweep across the card border */
        .card-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.06), transparent);
            transform: skewX(-25deg);
            animation: borderSweep 8s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes borderSweep {
            0%, 65% { left: -100%; }
            100% { left: 100%; }
        }

        /* Royal Crown Emblem */
        .crown-emblem {
            width: 82px;
            height: 82px;
            margin: 0 auto 1.25rem;
            position: relative;
            filter: drop-shadow(0 8px 18px rgba(212, 175, 55, 0.35));
            animation: crownPulse 4s ease-in-out infinite alternate;
        }

        @keyframes crownPulse {
            0% { transform: translateY(0) scale(1); filter: drop-shadow(0 6px 14px rgba(212, 175, 55, 0.3)); }
            100% { transform: translateY(-5px) scale(1.04); filter: drop-shadow(0 12px 28px rgba(255, 215, 0, 0.65)); }
        }

        /* 404 Large Display */
        .num-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 0.95;
            margin-bottom: 0.75rem;
        }

        .num-404 {
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

        .zero-emblem {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 38px;
            height: 38px;
            opacity: 0.22;
            color: #fef08a;
            pointer-events: none;
            animation: rotateSlow 24s linear infinite;
        }

        @keyframes rotateSlow {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(360deg); }
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
            font-size: clamp(1.45rem, 3.2vw, 1.95rem);
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
            margin: 0 auto 2rem;
            font-weight: 500;
        }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.9rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 540px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }
            .card-container {
                padding: 2.25rem 1.4rem 2rem;
                border-radius: 26px;
            }
        }

        .btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.95rem 1.4rem;
            border-radius: 16px;
            font-size: 0.98rem;
            font-family: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            border: 1px solid transparent;
        }

        /* Primary Royal Gold Button */
        .btn-gold {
            background: linear-gradient(135deg, #d4af37 0%, #aa8022 100%);
            color: #0b091a;
            box-shadow: 
                0 6px 20px -4px rgba(212, 175, 55, 0.45),
                0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .btn-gold::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.45), transparent);
            transform: skewX(-20deg);
            transition: 0.6s;
        }

        .btn-gold:hover::after {
            left: 150%;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 10px 28px -4px rgba(212, 175, 55, 0.65),
                0 4px 10px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #e5c37a 0%, #b88d2d 100%);
        }

        /* Secondary Royal Indigo Button */
        .btn-indigo {
            background: rgba(99, 102, 241, 0.12);
            color: #e0e7ff;
            border-color: rgba(129, 140, 248, 0.35);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15);
        }

        .btn-indigo:hover {
            background: rgba(99, 102, 241, 0.22);
            border-color: rgba(129, 140, 248, 0.65);
            transform: translateY(-2px);
            color: #ffffff;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        /* Tertiary Glass Buttons */
        .btn-glass {
            background: rgba(255, 255, 255, 0.04);
            color: #cbd5e1;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.28);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* Badge on Demo Button */
        .mini-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            background: #ef4444;
            color: #ffffff;
            font-weight: 800;
            line-height: 1;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
            animation: pulseMini 2s infinite;
        }

        @keyframes pulseMini {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        /* WhatsApp Direct Concierge Bar */
        .concierge-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: rgba(16, 185, 129, 0.06);
            border: 1px solid rgba(16, 185, 129, 0.22);
            border-radius: 20px;
            text-align: right;
            transition: all 0.25s ease;
        }

        [dir="ltr"] .concierge-box {
            text-align: left;
        }

        .concierge-box:hover {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.45);
        }

        .concierge-text h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .concierge-text p {
            font-size: 0.82rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1.15rem;
            background: #10b981;
            color: #042f2e;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-whatsapp:hover {
            background: #34d399;
            transform: scale(1.03);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
        }

        /* Footer Quote */
        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #64748b;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body>
    <!-- Ambient Background -->
    <div class="ambient-wrap">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="grid-texture"></div>
    </div>

    <!-- Top Navigation -->
    <header class="top-bar">
        <a href="/" class="brand-badge" title="منصة تدلّل السحابية">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e5c37a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
            </svg>
            <span id="txt-brand">تدلّل · Tedallal</span>
        </a>

        <button class="lang-btn" onclick="toggleLanguage()" aria-label="تبديل اللغة">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
                <path d="M2 12h20"/>
            </svg>
            <span id="lang-btn-label">English</span>
        </button>
    </header>

    <!-- Main Luxury Card -->
    <main class="card-container">
        <!-- Royal Animated Crown SVG -->
        <div class="crown-emblem">
            <svg viewBox="0 0 1022 997" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="crown-gold" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFF8DC" />
                        <stop offset="30%" stop-color="#E5C37A" />
                        <stop offset="70%" stop-color="#C59B50" />
                        <stop offset="100%" stop-color="#8F6920" />
                    </linearGradient>
                    <radialGradient id="crown-halo" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#FFDF73" stop-opacity="0.4" />
                        <stop offset="100%" stop-color="#D4AF37" stop-opacity="0" />
                    </radialGradient>
                </defs>
                <circle cx="512" cy="200" r="180" fill="url(#crown-halo)" />
                <path fill="url(#crown-gold)" d="M 334.00,298.50 C 340.83,296.02 357.64,289.31 371.25,285.00 C 384.86,280.69 393.40,278.16 408.25,275.00 C 423.10,271.84 436.99,269.58 452.25,267.75 C 467.51,265.92 480.41,265.50 491.50,265.00 C 501.05,264.57 503.86,264.68 512.75,265.00 C 521.64,265.32 527.79,265.39 540.00,266.75 C 552.79,268.17 566.64,269.63 582.50,272.75 C 598.36,275.87 610.96,279.03 626.50,283.75 C 642.04,288.47 659.78,295.80 667.25,298.50 C 676.37,273.48 707.56,187.30 717.00,162.00 C 717.36,161.03 717.75,160.76 718.75,160.50 C 720.35,160.09 723.14,160.62 725.75,159.75 C 728.36,158.88 730.66,157.72 733.00,155.75 C 735.34,153.78 737.03,151.66 738.50,149.00 C 739.97,146.34 740.54,143.77 741.00,141.25 C 741.46,138.73 741.23,137.27 741.00,135.25 C 740.77,133.23 740.53,132.18 739.75,130.25 C 738.97,128.32 738.12,126.58 736.75,124.75 C 735.38,122.92 734.22,121.67 732.25,120.25 C 730.28,118.83 728.02,117.78 726.00,117.00 C 723.98,116.22 723.43,116.04 721.25,116.00 C 719.00,115.95 716.13,116.20 713.75,116.75 C 711.37,117.30 710.48,117.53 708.25,119.00 C 705.96,120.51 703.13,122.85 701.25,125.00 C 699.37,127.15 698.75,127.87 698.00,130.75 C 697.22,133.73 696.59,137.63 697.00,141.25 C 697.41,144.87 699.65,148.80 700.25,150.50 L 587.25,243.50 L 564.00,191.50 L 599.50,154.50 L 612.00,203.75 C 612.23,203.75 612.78,204.06 613.25,203.75 C 616.32,201.69 625.91,194.56 628.75,192.50 C 628.57,191.12 628.51,188.32 627.75,185.00 C 625.28,174.14 617.54,142.74 615.25,133.25 C 617.08,132.29 622.45,130.29 625.25,128.00 C 628.05,125.71 629.17,123.41 630.50,120.75 C 631.83,118.09 632.45,116.75 632.50,113.50 C 632.55,110.25 631.99,106.21 630.75,103.00 C 629.51,99.79 628.22,98.20 625.75,96.00 C 623.28,93.80 619.54,92.05 617.25,91.00 C 615.59,90.23 615.08,90.25 613.25,90.25 C 610.96,90.25 607.64,90.13 604.75,91.00 C 601.86,91.87 599.61,93.40 597.50,95.00 C 595.39,96.60 594.44,98.10 593.25,99.75 C 592.06,101.40 591.62,101.93 591.00,104.00 C 590.36,106.15 589.75,108.75 589.75,111.50 C 589.75,114.25 589.81,116.07 591.00,119.00 C 592.19,121.93 595.29,125.94 596.25,127.50 L 555.00,171.00 L 513.00,76.75 C 513.18,76.38 513.28,75.45 514.00,74.75 C 515.38,73.42 518.53,71.79 520.50,69.50 C 522.47,67.21 523.65,65.41 524.75,62.25 C 525.85,59.09 526.55,55.83 526.50,52.25 C 526.45,48.67 525.74,45.87 524.50,42.75 C 523.26,39.63 522.13,37.72 519.75,35.25 C 517.37,32.78 514.43,30.76 511.50,29.25 C 508.57,27.74 507.28,27.23 503.75,27.00 C 500.22,26.77 495.64,27.17 492.25,28.00 C 488.86,28.83 487.40,30.03 485.25,31.50 C 483.10,32.97 482.15,33.85 480.50,36.00 C 478.85,38.15 477.35,40.41 476.25,43.25 C 475.15,46.09 474.73,48.80 474.50,51.50 C 474.27,54.20 474.31,55.25 475.00,58.00 C 475.69,60.75 476.88,63.98 478.25,66.50 C 479.62,69.02 480.76,70.10 482.50,71.75 C 484.24,73.40 486.79,74.81 487.75,75.50 C 487.75,75.73 487.98,76.24 487.75,76.75 C 480.00,94.26 453.25,153.72 445.50,171.00 L 404.75,128.00 C 405.85,125.94 409.60,120.42 410.75,116.75 C 411.90,113.08 411.37,110.84 411.00,108.00 C 410.63,105.16 410.12,103.63 408.75,101.25 C 407.38,98.87 406.07,96.93 403.50,95.00 C 400.93,93.07 397.77,91.62 394.75,90.75 C 391.73,89.88 389.06,90.20 387.00,90.25 C 385.39,90.29 384.97,90.35 383.50,91.00 C 381.44,91.92 377.86,93.69 375.75,95.25 C 373.70,96.77 373.19,97.76 372.00,99.50 C 370.81,101.24 369.92,102.17 369.25,104.75 C 368.56,107.41 367.93,110.75 368.25,114.00 C 368.57,117.25 369.26,119.61 371.00,122.50 C 372.74,125.39 375.05,127.78 377.75,129.75 C 380.45,131.72 384.28,132.61 385.75,133.25 C 383.23,143.33 374.57,177.39 372.00,188.25 C 371.56,190.11 371.80,191.72 371.75,192.50 C 374.50,194.52 383.68,201.53 386.75,203.50 C 387.42,203.93 388.18,203.30 388.50,203.25 C 390.61,195.23 397.52,168.39 400.00,159.50 C 400.62,157.27 401.63,155.62 402.00,154.75 L 436.50,191.50 C 432.88,199.89 421.01,227.81 416.75,237.25 C 415.50,240.01 413.89,241.95 413.25,243.00 L 301.00,150.75 C 301.55,149.33 303.40,145.89 304.00,143.00 C 304.60,140.11 304.66,137.84 304.25,135.00 C 303.84,132.16 303.26,130.11 301.75,127.50 C 300.24,124.89 298.57,122.72 296.00,120.75 C 293.43,118.78 290.00,117.62 287.75,116.75 C 286.04,116.09 285.58,116.00 283.75,116.00 C 281.73,116.00 279.68,115.79 276.75,116.75 C 273.82,117.71 270.45,119.05 267.75,121.25 C 265.05,123.45 263.42,126.05 262.00,128.75 C 260.58,131.45 260.37,133.80 260.00,136.00 C 259.65,138.11 259.68,138.69 260.00,140.75 C 260.32,142.81 260.70,144.91 261.75,147.25 C 262.80,149.59 264.05,151.53 265.75,153.50 C 267.45,155.47 269.17,156.81 271.00,158.00 C 272.83,159.19 273.60,159.50 275.75,160.00 C 277.90,160.50 281.24,160.38 282.75,160.75 C 283.52,160.94 283.72,161.25 284.00,162.00 C 293.40,187.25 324.83,273.48 334.00,298.50 Z" />
            </svg>
        </div>

        <!-- 404 Number -->
        <div class="num-wrap">
            <span class="num-404">404</span>
            <svg class="zero-emblem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10" stroke-dasharray="3 3"/>
                <polygon points="12,2 15,9 22,9 17,14 19,21 12,17 5,21 7,14 2,9 9,9"/>
            </svg>
        </div>

        <!-- Status Badge -->
        <div>
            <div class="status-badge">
                <span class="pulse-dot"></span>
                <span id="txt-badge">✦ رمز الخطأ 404 · الطاولة غير موجودة ✦</span>
            </div>
        </div>

        <!-- Headline & Description -->
        <h1 class="headline" id="txt-headline">يبدو أنك وصلت إلى طاولة خارج الصالة!</h1>
        <p class="description" id="txt-desc">
            الصفحة أو الرابط الذي تبحث عنه قد تم نقله أو تغييره، أو ربما لم يُضف بعد إلى قائمة طعامنا الفاخرة. لا تقلق، ضيافة تدلّل بانتظارك في كل ركن.
        </p>

        <!-- Actions Grid -->
        <div class="actions-grid">
            <!-- 1. Go Home -->
            <a href="/" class="btn btn-gold" id="btn-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span id="txt-btn-home">العودة للرئيسية</span>
            </a>

            <!-- 2. Interactive Demo -->
            <a href="/demo" class="btn btn-indigo" id="btn-demo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
                <span id="txt-btn-demo">محاكي المنصة 60 ثانية</span>
                <span class="mini-badge" id="txt-badge-new">جديد</span>
            </a>

            <!-- 3. Provisioning & Partnership -->
            <a href="/consultation" class="btn btn-glass" id="btn-partner">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e5c37a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                <span id="txt-btn-partner">تدشين مطعمك مع تدلّل</span>
            </a>

            <!-- 4. Go Back -->
            <button onclick="goBack()" class="btn btn-glass" id="btn-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span id="txt-btn-back">الرجوع خطوة للخلف</span>
            </button>
        </div>

        <!-- Direct WhatsApp Concierge -->
        <div class="concierge-box">
            <div class="concierge-text">
                <h4 id="txt-concierge-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
                    </svg>
                    <span id="txt-concierge-head">كونسيرج تدلّل المباشر</span>
                </h4>
                <p id="txt-concierge-sub">هل تبحث عن صالة معينة أو تواجه مشكلة؟ تواصل فوراً مع إدارة المنصة.</p>
            </div>
            <a id="whatsapp-link" href="https://wa.me/963947064593?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%20%D8%A5%D8%AF%D8%A7%D8%B1%D8%A9%20%D8%AA%D8%AF%D9%84%D9%91%D9%84%D8%8C%20%D9%88%D8%B5%D9%84%D8%AA%20%D8%A5%D9%84%D9%89%20%D8%B5%D9%81%D8%AD%D8%A9%20404%20%D9%88%D8%A3%D8%AD%D8%AA%D8%A7%D8%AC%20%D9%85%D8%B3%D8%A7%D8%B9%D8%AF%D8%A9." target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                <span id="txt-whatsapp-btn">محادثة واتساب</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <p class="footer-note" id="txt-footer">
            منظومة تدلّل السحابية © {{ date('Y') }} · الفخامة في كل تفصيل
        </p>
    </main>

    <!-- Bilingual & Dynamic Handler -->
    <script>
        let currentLang = 'ar';

        const i18n = {
            ar: {
                title: '404 · الطاولة غير موجودة | تدلّل Tedallal',
                brand: 'تدلّل · Tedallal',
                badge: '✦ رمز الخطأ 404 · الطاولة غير موجودة ✦',
                headline: 'يبدو أنك وصلت إلى طاولة خارج الصالة!',
                desc: 'الصفحة أو الرابط الذي تبحث عنه قد تم نقله أو تغييره، أو ربما لم يُضف بعد إلى قائمة طعامنا الفاخرة. لا تقلق، ضيافة تدلّل بانتظارك في كل ركن.',
                btnHome: 'العودة للرئيسية',
                btnDemo: 'محاكي المنصة 60 ثانية',
                badgeNew: 'جديد',
                btnPartner: 'تدشين مطعمك مع تدلّل',
                btnBack: 'الرجوع خطوة للخلف',
                conciergeHead: 'كونسيرج تدلّل المباشر',
                conciergeSub: 'هل تبحث عن صالة معينة أو تواجه مشكلة؟ تواصل فوراً مع إدارة المنصة.',
                whatsappBtn: 'محادثة واتساب',
                footer: 'منظومة تدلّل السحابية © 2026 · الفخامة في كل تفصيل',
                langLabel: 'English'
            },
            en: {
                title: '404 · Table Not Found | Tedallal',
                brand: 'Tedallal · تدلّل',
                badge: '✦ ERROR 404 · UNRESERVED SPOT ✦',
                headline: 'Looks like you discovered an unreserved table!',
                desc: 'The page or link you are seeking has been moved, modified, or is yet to be added to our premier dining menu. Rest assured, Tedallal hospitality is always at your service.',
                btnHome: 'Return to Homepage',
                btnDemo: '60s Interactive Sandbox',
                badgeNew: 'NEW',
                btnPartner: 'Launch Your Restaurant',
                btnBack: 'Go Back One Step',
                conciergeHead: 'Tedallal Direct Concierge',
                conciergeSub: 'Looking for a specific hall or facing an issue? Reach out to management directly.',
                whatsappBtn: 'Chat on WhatsApp',
                footer: 'Tedallal Cloud Platform © 2026 · Luxury in Every Detail',
                langLabel: 'العربية'
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
            document.getElementById('txt-brand').textContent = data.brand;
            document.getElementById('txt-badge').textContent = data.badge;
            document.getElementById('txt-headline').textContent = data.headline;
            document.getElementById('txt-desc').textContent = data.desc;
            document.getElementById('txt-btn-home').textContent = data.btnHome;
            document.getElementById('txt-btn-demo').textContent = data.btnDemo;
            document.getElementById('txt-badge-new').textContent = data.badgeNew;
            document.getElementById('txt-btn-partner').textContent = data.btnPartner;
            document.getElementById('txt-btn-back').textContent = data.btnBack;
            document.getElementById('txt-concierge-head').textContent = data.conciergeHead;
            document.getElementById('txt-concierge-sub').textContent = data.conciergeSub;
            document.getElementById('txt-whatsapp-btn').textContent = data.whatsappBtn;
            document.getElementById('txt-footer').textContent = data.footer;
            document.getElementById('lang-btn-label').textContent = data.langLabel;
        }

        function goBack() {
            if (window.history && window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        }

        // Dynamically enrich WhatsApp message with current URL
        try {
            const currentUrl = encodeURIComponent(window.location.href);
            const waLink = document.getElementById('whatsapp-link');
            if (waLink) {
                waLink.href = `https://wa.me/963947064593?text=${encodeURIComponent('مرحباً إدارة تدلّل، كنت أتصفح الرابط التالي وظهرت لي صفحة 404:\n' + window.location.href)}`;
            }
        } catch (e) {}

        // Auto detect browser language
        (function detectLocale() {
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
