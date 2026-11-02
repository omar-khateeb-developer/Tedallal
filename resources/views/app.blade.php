<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800|inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased" style="font-family: 'Cairo', sans-serif;">
        @inertia
    </body>
</html>
