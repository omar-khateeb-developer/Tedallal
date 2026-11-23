<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بطاقات QR لطاولات المطعم — {{ $restaurant->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800,900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', sans-serif;
            background: #0f172a;
            color: #1e293b;
            padding: 24px 16px;
            min-height: 100vh;
        }

        /* Toolbar */
        .toolbar {
            max-width: 1100px;
            margin: 0 auto 24px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 14px 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar select, .toolbar button, .toolbar a {
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            padding: 9px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(217,119,6,0.3);
        }
        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .toolbar select {
            background: #0f172a;
            color: #f1f5f9;
            border: 1px solid #475569;
            padding: 8px 12px;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Card: Luxury Gold Theme */
        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 20px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
            break-inside: avoid;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s;
        }
        .card.theme-dark {
            background: #0b1120;
            border: 2px solid #d97706;
            color: #f8fafc;
            box-shadow: 0 10px 30px -5px rgba(217,119,6,0.25);
        }

        /* Header elements */
        .restaurant-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }
        .restaurant-logo {
            max-height: 40px;
            max-width: 140px;
            object-fit: contain;
            margin: 0 auto 6px;
            display: block;
        }
        .restaurant-name {
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .card.theme-dark .restaurant-name {
            color: #fbbf24;
        }
        .table-pill {
            display: inline-block;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 4px 18px;
            border-radius: 999px;
            font-size: 18px;
            font-weight: 900;
            margin: 4px auto 14px;
            border: 1px solid #fcd34d;
        }
        .card.theme-dark .table-pill {
            background: linear-gradient(135deg, #d97706, #b45309);
            color: #ffffff;
            border: 1px solid #f59e0b;
        }

        /* QR Frame */
        .qr-wrapper {
            background: #ffffff;
            padding: 12px;
            border-radius: 16px;
            display: inline-block;
            margin: 0 auto 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border: 2px solid #f1f5f9;
        }
        .card.theme-dark .qr-wrapper {
            border: 2px solid #d97706;
            box-shadow: 0 4px 16px rgba(217,119,6,0.3);
        }
        .qr-wrapper img {
            width: 190px;
            height: 190px;
            display: block;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        /* Call To Action */
        .cta-title {
            font-size: 15px;
            font-weight: 800;
            color: #d97706;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .cta-sub {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        .card.theme-dark .cta-sub {
            color: #94a3b8;
        }
        .watermark {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.3px;
        }
        .card.theme-dark .watermark {
            color: #64748b;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .toolbar { display: none !important; }
            .grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 12px !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px dashed #94a3b8 !important;
                border-radius: 12px !important;
                padding: 18px 14px !important;
            }
            .card.theme-dark {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                color: #000000 !important;
            }
            .card.theme-dark .restaurant-name { color: #000000 !important; }
            .card.theme-dark .table-pill {
                background: #000000 !important;
                color: #ffffff !important;
                border: none !important;
            }
            .card.theme-dark .cta-sub { color: #475569 !important; }
            .card.theme-dark .qr-wrapper { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-group">
            <button type="button" class="btn-primary" onclick="window.print()">
                🖨️ طباعة البطاقات الآن
            </button>
            <button type="button" class="btn-secondary" id="themeToggleBtn" onclick="toggleTheme()">
                🎨 التبديل للستايل الفاخر (Dark Gold)
            </button>
        </div>

        <div class="toolbar-group">
            <label style="color:#94a3b8; font-size:12px; font-weight:700;">طاولة محددة:</label>
            <select onchange="filterByTable(this.value)">
                <option value="">جميع الطاولات ({{ count($allTables) }})</option>
                @foreach ($allTables as $t)
                    <option value="{{ $t->id }}" {{ $selectedTableId === $t->id ? 'selected' : '' }}>
                        {{ $t->name }}
                    </option>
                @endforeach
            </select>

            <a href="{{ route('admin.tables') }}" class="btn-secondary">
                العودة للطاولات ✕
            </a>
        </div>
    </div>

    <div class="grid" id="cardsGrid">
        @foreach ($tables as $table)
            <article class="card">
                <div>
                    @if ($restaurant->logo)
                        <img src="{{ $restaurant->logo }}" alt="{{ $restaurant->name }}" class="restaurant-logo">
                    @else
                        <div class="restaurant-badge">
                            <span style="font-size:18px;">👑</span>
                            <span class="restaurant-name">{{ $restaurant->name }}</span>
                        </div>
                    @endif
                    <div class="table-pill">{{ $table['name'] }}</div>
                </div>

                <div class="qr-wrapper">
                    <img src="data:image/png;base64,{{ $table['qr_base64'] }}" alt="QR {{ $table['name'] }}">
                </div>

                <div>
                    <div class="cta-title">
                        <span>📱</span>
                        <span>امسح بكاميرا الهاتف للطلب</span>
                    </div>
                    <p class="cta-sub">
                        تصفح المنيو الرقمي مباشرة • اطلب بدون انتظار • شارك عجلة الحظ
                    </p>
                    <div class="watermark">
                        <span dir="ltr">Powered by: www.tedallal.tech</span> • منظومة «تدلّل» الذكية للضيافة
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <script>
        let isDarkTheme = false;
        function toggleTheme() {
            isDarkTheme = !isDarkTheme;
            const cards = document.querySelectorAll('.card');
            const btn = document.getElementById('themeToggleBtn');
            cards.forEach(card => {
                if (isDarkTheme) {
                    card.classList.add('theme-dark');
                } else {
                    card.classList.remove('theme-dark');
                }
            });
            btn.textContent = isDarkTheme ? '🎨 التبديل للستايل الأبيض العصري' : '🎨 التبديل للستايل الفاخر (Dark Gold)';
        }

        function filterByTable(tableId) {
            const url = new URL(window.location.href);
            if (tableId) {
                url.searchParams.set('table_id', tableId);
            } else {
                url.searchParams.delete('table_id');
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
