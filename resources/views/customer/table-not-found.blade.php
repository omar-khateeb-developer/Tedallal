<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تنبيه الطاولة · تدلّل للضيافة الفاخرة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            min-height: 100vh;
            background: radial-gradient(circle at 50% 20%, #171410 0%, #0c0b09 60%, #050504 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cairo', system-ui, -apple-system, sans-serif;
            padding: 1.5rem;
            color: #ffffff;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Gold Spotlight */
        .ambient-glow {
            position: absolute;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 500px;
            height: 350px;
            background: radial-gradient(ellipse at center, rgba(218, 165, 32, 0.22) 0%, rgba(180, 140, 60, 0.05) 50%, transparent 75%);
            filter: blur(40px);
            pointer-events: none;
        }

        .card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            background: linear-gradient(145deg, rgba(30, 26, 20, 0.92) 0%, rgba(14, 13, 11, 0.96) 100%);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 28px;
            padding: 2.25rem 1.75rem;
            text-align: center;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.85), inset 0 1px 1px 0 rgba(255, 255, 255, 0.2);
        }

        .logo-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.25rem;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.2) 0%, rgba(180, 140, 60, 0.05) 100%);
            border: 1px solid rgba(212, 175, 55, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 25px rgba(212, 175, 55, 0.15);
        }
        .logo-wrap img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            filter: drop-shadow(0 4px 10px rgba(218, 165, 32, 0.4));
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 9999px;
            background: rgba(212, 175, 55, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.35);
            color: #f59e0b;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: 0.5px;
        }

        h1 {
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1.35;
            margin-bottom: 0.75rem;
            color: #ffffff;
            letter-spacing: -0.3px;
        }

        p.desc {
            font-size: 0.925rem;
            line-height: 1.65;
            color: #d6d3d1;
            margin-bottom: 1.5rem;
        }

        .info-box {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.825rem;
            color: #a8a29e;
            text-align: right;
            line-height: 1.5;
        }
        .info-box span.token-display {
            font-family: monospace;
            color: #fbbf24;
            direction: ltr;
            display: inline-block;
            word-break: break-all;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-gold {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 0.9rem 1.5rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #d4af37 0%, #b8860b 50%, #996515 100%);
            color: #0c0b08;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
            transition: all 0.25s ease;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(212, 175, 55, 0.45);
        }

        .btn-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 0.85rem 1.5rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            color: #e7e5e4;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(212, 175, 55, 0.4);
            color: #fbbf24;
        }

        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #78716c;
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>

    <main class="card">
        <!-- Golden Logo -->
        <div class="logo-wrap">
            <img src="/images/tedallal-golden-logo-pure.png" alt="تدلّل">
        </div>

        <!-- Status Badge -->
        <div class="badge">
            <span>⚜️</span>
            <span>منظومة تدلّل للضيافة الراقية</span>
        </div>

        @if (isset($reason) && $reason === 'inactive')
            <h1>هذه الطاولة غير مفعّلة حالياً</h1>
            <p class="desc">
                طاولتك الكريمة قيد التهيئة أو التبديل. يرجى إبلاغ طاقم الضيافة لتفعيل رمز الطاولة فوراً والبدء بتجربة طعام مميزة.
            </p>
        @elseif (isset($reason) && $reason === 'restaurant_closed')
            <h1>المطعم غير متاح للطلبات حالياً</h1>
            <p class="desc">
                نعتذر منكم، المطعم مغلق حالياً أو لا يستقبل طلبات جديدة في هذه اللحظة. نتشرف بخدمتكم في أوقات العمل الرسمية.
            </p>
        @else
            <h1>لم نتمكن من العثور على الطاولة</h1>
            <p class="desc">
                رمز الاستجابة السريعة (QR) الحالي غير مسجل أو ربما انتهت صلاحيته. يرجى مسح الرمز المثبت على طاولتكم مجدداً أو الاستعانة بموظف الخدمة.
            </p>
        @endif

        @if (isset($token) && $token)
            <div class="info-box">
                <div>معرّف الرمز الممسوح:</div>
                <span class="token-display">{{ $token }}</span>
            </div>
        @endif

        <div class="actions">
            <button type="button" class="btn-gold" onclick="window.location.reload()">
                <span>إعادة المحاولة ومسح الرمز</span>
                <span>↻</span>
            </button>
            <a href="/" class="btn-secondary">
                <span>الصفحة الرئيسية لمنصة تدلل</span>
                <span>←</span>
            </a>
        </div>

        <p class="footer-note">
            Tedallal Smart Hospitality Suite © {{ date('Y') }}
        </p>
    </main>
</body>
</html>

