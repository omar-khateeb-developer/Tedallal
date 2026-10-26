<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة الطلب #{{ $order->order_number }}</title>
    <style>
        /* إعدادات خط طابعات 80mm */
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

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
            font-size: 14px;
            line-height: 1.4;
        }

        .receipt-container {
            padding: 5mm;
            width: 80mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 5mm;
        }

        .restaurant-name {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 2mm 0;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 3mm 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            font-size: 13px;
        }
        
        .info-row .label {
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4mm 0;
            font-size: 13px;
        }

        th {
            border-bottom: 1px dashed #000;
            padding-bottom: 2mm;
            text-align: right;
            font-weight: 700;
        }
        th.text-left { text-align: left; }
        th.text-center { text-align: center; }

        td {
            padding: 2mm 0;
            vertical-align: top;
        }
        td.text-left { text-align: left; }
        td.text-center { text-align: center; }

        .item-name {
            font-weight: 500;
        }
        .item-note {
            font-size: 11px;
            color: #444;
            display: block;
            margin-top: 1px;
        }

        .totals {
            margin-top: 4mm;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 16px;
            margin-top: 2mm;
        }

        .footer {
            text-align: center;
            margin-top: 6mm;
            font-size: 12px;
            font-weight: 500;
        }

        /* إعدادات الطباعة للورق الحراري */
        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
            }
            body {
                width: 80mm;
                margin: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">

<div class="receipt-container">
    <div class="header">
        <h1 class="restaurant-name">{{ $restaurant->name }}</h1>
        <div>فاتورة طلب</div>
    </div>

    <div class="divider"></div>

    <div class="info-row">
        <span class="label">رقم الطلب:</span>
        <span>#{{ $order->order_number }}</span>
    </div>
    
    <div class="info-row">
        <span class="label">التاريخ:</span>
        <span dir="ltr">{{ $order->created_at?->format('Y-m-d h:i A') ?? now()->format('Y-m-d h:i A') }}</span>
    </div>

    <div class="info-row">
        <span class="label">نوع الطلب:</span>
        <span>{{ ($order->service_type ?? 'dine_in') === 'takeaway' ? 'سفري / استلام' : 'محلي / صالة' }}</span>
    </div>

    @if($order->pickup_window)
    <div class="info-row" style="background: #f1f5f9; padding: 1mm 2mm; border-radius: 4px; margin: 1mm 0;">
        <span class="label" style="color: #0f172a; font-weight: 800;">شباك الاستلام:</span>
        <span style="font-weight: 800; color: #0284c7; font-size: 15px;">كوة رقم {{ $order->pickup_window }}</span>
    </div>
    @endif

    @if($order->table)
    <div class="info-row">
        <span class="label">الطاولة:</span>
        <span>{{ $order->table->name }}</span>
    </div>
    @endif

    @if($order->paid_at)
    <div class="info-row">
        <span class="label">حالة الدفع:</span>
        <span style="color: #16a34a; font-weight: 700;">مدفوع للكاشير ✓</span>
    </div>
    @endif

    @if($order->customer_note)
    <div class="info-row" style="flex-direction: column; align-items: flex-start; margin-top: 2mm; background: #fffbeb; padding: 2mm; border: 1px dashed #d97706; border-radius: 2mm;">
        <span class="label" style="color: #b45309; font-size: 12px;">ملاحظات الزبون:</span>
        <span style="font-weight: 700; margin-top: 1mm; font-size: 13px;">{{ $order->customer_note }}</span>
    </div>
    @endif

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th>الصنف</th>
                <th class="text-center">الكمية</th>
                <th class="text-left">السعر</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>
                    <div class="item-name">{{ $item->item_name_snapshot }}</div>
                    @if($item->note)
                        <span class="item-note">- {{ $item->note }}</span>
                    @endif
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-left" dir="ltr">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="totals">
        <div class="total-row">
            <span>الإجمالي:</span>
            <span dir="ltr">{{ number_format($order->total, 2) }} {{ $restaurant->currency }}</span>
        </div>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p>شكراً لزيارتكم! نتمنى لكم يوماً سعيداً.</p>
    </div>
</div>

</body>
</html>
