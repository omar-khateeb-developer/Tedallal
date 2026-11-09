<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير إغلاق الوردية (Z-Report) - {{ $restaurant->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

        * {
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            width: 80mm;
            color: #000;
            background: #fff;
            font-size: 12px;
            line-height: 1.35;
        }

        .receipt-container {
            padding: 3mm 3mm;
            width: 80mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 2mm;
        }

        .restaurant-name {
            font-size: 17px;
            font-weight: 900;
            margin: 0 0 1mm 0;
        }

        .badge-title {
            display: inline-block;
            font-size: 13px;
            font-weight: 800;
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 1.5mm 3mm;
            margin: 1.5mm 0;
            background: #fff;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 2mm 0;
        }

        .meta-table td {
            padding: 1mm 0;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }

        .double-divider {
            border-top: 2px solid #000;
            margin: 2.5mm 0;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 1mm 0;
            font-size: 11.5px;
        }

        .stat-row.bold {
            font-weight: 800;
            font-size: 12px;
        }

        .stat-row.highlight {
            font-size: 14px;
            font-weight: 900;
            background: #f0f0f0;
            padding: 2mm 1.5mm;
            border-radius: 4px;
            margin: 1.5mm 0;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            font-weight: 600;
            margin-top: 3mm;
            color: #444;
        }

        @media print {
            body {
                width: 80mm;
            }

            @page {
                margin: 0;
                size: 80mm auto;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="background: #2563eb; color: #fff; text-align: center; padding: 8px; font-weight: bold; cursor: pointer;" onclick="window.print()">
        🖨️ انقر هنا لطباعة تقرير إغلاق الوردية
    </div>

    <div class="receipt-container">
        <div class="header">
            <h1 class="restaurant-name">{{ $restaurant->name }}</h1>
            <div class="badge-title">📊 تقرير إغلاق الوردية (Z-REPORT)</div>
        </div>

        <table class="meta-table">
            <tr>
                <td><strong>التاريخ:</strong> {{ $reportDate }}</td>
                <td style="text-align: left;"><strong>الكاشير:</strong> {{ $cashierName }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>وقت الطباعة:</strong> {{ $printedAt }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <div style="font-weight: 800; font-size: 12px; margin-bottom: 1mm;">📦 حركة الطلبات اليومية:</div>
        <div class="stat-row">
            <span>إجمالي الطلبات المستلمة:</span>
            <strong>{{ $totalOrders }}</strong>
        </div>
        <div class="stat-row">
            <span>الطلبات المدفوعة والمكتملة:</span>
            <strong>{{ $paidOrdersCount }}</strong>
        </div>
        <div class="stat-row">
            <span>طلبات محلي (صالة):</span>
            <strong>{{ $dineInCount }}</strong>
        </div>
        <div class="stat-row">
            <span>طلبات سفري (Takeaway):</span>
            <strong>{{ $takeawayCount }}</strong>
        </div>
        <div class="stat-row">
            <span>طلبات ملغاة:</span>
            <strong>{{ $cancelledOrdersCount }}</strong>
        </div>
        <div class="stat-row">
            <span>طلبات بانتظار المحاسبة:</span>
            <strong>{{ $pendingOrdersCount }}</strong>
        </div>

        <div class="divider"></div>

        <div style="font-weight: 800; font-size: 12px; margin-bottom: 1mm;">💰 الحسابات المالية:</div>
        <div class="stat-row">
            <span>المجموع الفرعي (Subtotal):</span>
            <span>{{ $totalSubtotal }} {{ $restaurant->currency }}</span>
        </div>
        <div class="stat-row">
            <span>إجمالي الخصومات (كوبونات وعجلة الحظ):</span>
            <span style="color: #b91c1c;">- {{ $totalDiscount }} {{ $restaurant->currency }}</span>
        </div>

        <div class="double-divider"></div>

        <div class="stat-row highlight">
            <span>صافي المبيعات (المحصل بالصندوق):</span>
            <span>{{ $totalSales }} {{ $restaurant->currency }}</span>
        </div>

        <div class="divider"></div>

        <div class="footer">
            <div>منظومة تدلّل السحابية الذكية لإدارة ونمو المطاعم</div>
            <div style="direction: ltr; margin-top: 1mm;">tedallal.tech</div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            // Auto open print dialog
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>

</html>

