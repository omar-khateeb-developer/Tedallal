<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'تدلّل') }}</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="تدلّل | المنظومة السحابية الذكية لإدارة ونمو المطاعم">
        <meta name="description" content="منظومة متكاملة لأتمتة صالة ومطبخ المطعم: طلب ذاتي عبر الباركود، شاشات مطبخ KDS، إدارة الطاولات، ومضاعفة الأرباح بنسبة 100% بدون عمولات وسيطة.">
        <meta name="keywords" content="منيو الكتروني, نظام مطاعم, باركود طاولات, شاشات مطبخ, تدلل, نقاط بيع مطاعم, KDS, POS, Restaurant Cloud Management">
        <meta name="author" content="Tedallal Platform">

        <!-- Open Graph / Facebook / WhatsApp -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="تدلّل | Tedallal">
        <meta property="og:title" content="تدلّل | المنظومة السحابية الذكية لإدارة ونمو المطاعم">
        <meta property="og:description" content="منظومة متكاملة لأتمتة صالة ومطبخ المطعم: طلب ذاتي عبر الباركود، شاشات مطبخ KDS، وإيقاف نزيف العمولات لزيادة أرباحك فورياً.">
        <meta property="og:image" content="{{ asset('images/tedallal-social-card.jpg') }}">
        <meta property="og:image:secure_url" content="{{ asset('images/tedallal-social-card.jpg') }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="تدلّل - المنظومة السحابية لأتمتة ونمو المطاعم">
        <meta property="og:locale" content="ar_AR">
        <link rel="image_src" href="{{ asset('images/tedallal-social-card.jpg') }}">
        <meta itemprop="image" content="{{ asset('images/tedallal-social-card.jpg') }}">

        <!-- Twitter / X -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ url()->current() }}">
        <meta name="twitter:title" content="تدلّل | المنظومة السحابية الذكية لإدارة ونمو المطاعم">
        <meta name="twitter:description" content="منظومة متكاملة لأتمتة صالة ومطبخ المطعم: طلب ذاتي عبر الباركود، شاشات مطبخ KDS، ومضاعفة الأرباح بدون عمولات وسيطة.">
        <meta name="twitter:image" content="{{ asset('images/tedallal-social-card.jpg') }}">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#060913">

        <!-- Progressive Web App (PWA) iOS/Android Support -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="تدلّل">
        <meta name="application-name" content="تدلّل">

        <script>
            (function() {
                try {
                    var theme = localStorage.getItem('tedallal_theme') || localStorage.getItem('tedallal_customer_theme');
                    if (theme === 'light') {
                        document.documentElement.classList.remove('dark');
                        document.documentElement.style.colorScheme = 'light';
                    } else {
                        document.documentElement.classList.add('dark');
                        document.documentElement.style.colorScheme = 'dark';
                    }
                } catch (e) {}
                try {
                    var path = window.location.pathname || '';
                    var isCustomerRoute = /^\/(t|m|wheel|wheel-demo|w|r|book)(\/|$)/.test(path);
                    var minScreen = Math.min(window.screen.width || 9999, window.screen.height || 9999);
                    var isCoarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
                    var hasTouch = (navigator.maxTouchPoints || 0) > 0 || isCoarse;
                    var iw = window.innerWidth || 0;
                    var ih = window.innerHeight || 1;
                    var isPortraitTall = (ih / iw) >= 1.65;
                    var isPhoneDesktopMode = isCustomerRoute && hasTouch && iw >= 750 && (minScreen <= 560 || (isCoarse && isPortraitTall && iw >= 850));
                    if (isPhoneDesktopMode) {
                        document.documentElement.classList.add('tld-desktop-mode-phone');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- High-End Luxury Hospitality Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800,900|tajawal:400,500,700,800|amiri:400,700|cinzel:600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased" style="font-family: 'Cairo', sans-serif;">
        @include('partials.app-splash')
        @inertia

        {{-- Fallback Recovery: In case of network timeout, chunk loading, or script failure --}}
        <script>
            (function() {
                setTimeout(function() {
                    var app = document.getElementById('app');
                    if (app && (!app.children || app.children.length === 0)) {
                        var fallbackId = 'tedallal-app-fallback';
                        if (!document.getElementById(fallbackId)) {
                            var div = document.createElement('div');
                            div.id = fallbackId;
                            div.setAttribute('dir', 'rtl');
                            div.style.cssText = 'position:fixed;inset:0;z-index:9999998;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#060913;color:#fff;padding:24px;text-align:center;font-family:Cairo,sans-serif;';
                            div.innerHTML = '<div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);padding:32px 24px;border-radius:24px;max-width:380px;width:100%;box-shadow:0 20px 40px rgba(0,0,0,0.5);">' +
                                '<div style="font-size:42px;margin-bottom:12px;">⚡</div>' +
                                '<h3 style="font-size:18px;font-weight:800;color:#f3f4f6;margin-bottom:8px;">تعذر اكتمال تحميل الصفحة</h3>' +
                                '<p style="font-size:13px;color:#9ca3af;margin-bottom:20px;line-height:1.6;">يبدو أن هناك بطء في الاتصال أو تحديثاً في ملفات النظام، اضغط أدناه لإعادة التحميل.</p>' +
                                '<button onclick="window.location.reload(true)" style="width:100%;background:linear-gradient(135deg,#d4af37,#f59e0b);color:#000;font-weight:800;font-size:14px;padding:12px 20px;border-radius:14px;border:none;cursor:pointer;box-shadow:0 4px 15px rgba(212,175,55,0.3);">' +
                                'إعادة المحاولة الآن' +
                                '</button>' +
                                '</div>';
                            document.body.appendChild(div);
                        }
                    }
                }, 5000);
            })();
        </script>
    </body>
</html>
