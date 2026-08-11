<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رموز QR — {{ $restaurant->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', sans-serif;
            background: #f5f5f4;
            color: #1c1917;
            padding: 24px;
        }
        .toolbar {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .toolbar button, .toolbar a {
            background: #d97706;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar a.secondary {
            background: #44403c;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border: 2px solid #e7e5e4;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .restaurant-name {
            font-size: 14px;
            color: #78716c;
            margin-bottom: 4px;
        }
        .table-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .qr img {
            width: 180px;
            height: 180px;
            margin: 0 auto 16px;
            display: block;
        }
        .instruction {
            font-size: 16px;
            font-weight: 600;
            color: #d97706;
            margin-bottom: 8px;
        }
        .url {
            font-size: 10px;
            color: #a8a29e;
            word-break: break-all;
            direction: ltr;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                max-width: none;
            }
            .card {
                border: 1px dashed #ccc;
                border-radius: 8px;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة الكل</button>
        <a href="{{ route('admin.tables') }}" class="secondary">العودة للطاولات</a>
    </div>

    <div class="grid">
        @foreach ($tables as $table)
            <article class="card">
                <p class="restaurant-name">{{ $restaurant->name }}</p>
                <h2 class="table-name">{{ $table['name'] }}</h2>
                <div class="qr">
                    <img src="data:image/png;base64,{{ $table['qr_base64'] }}" alt="QR {{ $table['name'] }}">
                </div>
                <p class="instruction">امسح للطلب</p>
                <p class="url">{{ $table['public_url'] }}</p>
            </article>
        @endforeach
    </div>
</body>
</html>
