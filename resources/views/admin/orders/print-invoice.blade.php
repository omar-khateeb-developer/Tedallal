<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة الطلب #{{ $order->order_number }}</title>
    <style>
        /* إعدادات خط طابعات 80mm حرارية ومضغوطة لتقصير طول الفاتورة */
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
            line-height: 1.3;
        }

        .receipt-container {
            padding: 2.5mm 3mm;
            width: 80mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 1.5mm;
        }

        .restaurant-name {
            font-size: 16px;
            font-weight: 900;
            margin: 0 0 1mm 0;
            line-height: 1.2;
        }

        .header-sub {
            font-size: 11.5px;
            font-weight: 600;
        }

        /* صندوق رقم الطلب المميز بخط عريض ومضغوط */
        .daily-order-box {
            text-align: center;
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 1.5mm 1mm;
            margin: 1.5mm 0;
            background: #fff;
        }

        .daily-order-label {
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 0.5mm;
        }

        .daily-order-number {
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
            font-family: monospace, 'Tajawal', sans-serif;
            letter-spacing: -0.5px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 1.5mm 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5mm;
            font-size: 11.5px;
            line-height: 1.25;
        }

        .info-row .label {
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5mm 0;
            font-size: 11.5px;
        }

        th {
            border-bottom: 1px dashed #000;
            padding-bottom: 1mm;
            text-align: right;
            font-weight: 800;
        }

        th.text-left {
            text-align: left;
        }

        th.text-center {
            text-align: center;
        }

        td {
            padding: 1mm 0;
            vertical-align: top;
        }

        td.text-left {
            text-align: left;
        }

        td.text-center {
            text-align: center;
        }

        .item-name {
            font-weight: 600;
        }

        .item-note {
            font-size: 10px;
            color: #444;
            display: block;
            margin-top: 1px;
        }

        .totals {
            margin-top: 1.5mm;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 900;
            font-size: 15px;
            margin-top: 1mm;
        }

        .footer {
            text-align: center;
            margin-top: 2mm;
            font-size: 10.5px;
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
            <div class="header-sub">فاتورة طلب</div>
        </div>

        <!-- رقم الطلب المميز في سطر مستقل وبخط عريض -->
        <div class="daily-order-box">
            <div class="daily-order-label">رقم الطلب</div>
            <div class="daily-order-number">#{{ $order->short_order_number }}</div>
        </div>

        <div class="divider"></div>

        <div class="info-row">
            <span class="label">الرمز المرجعي:</span>
            <span dir="ltr" style="font-family: monospace; font-size: 10.5px; font-weight: 700;">{{ $order->order_number }}</span>
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
        <div class="info-row" style="background: #f1f5f9; padding: 0.5mm 1.5mm; border-radius: 3px; margin: 0.5mm 0;">
            <span class="label" style="color: #0f172a; font-weight: 800;">نافذة الاستلام:</span>
            <span style="font-weight: 800; color: #0284c7; font-size: 13px;">نافذة رقم {{ $order->pickup_window }}</span>
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
        <div class="info-row" style="flex-direction: column; align-items: flex-start; margin-top: 1mm; background: #fffbeb; padding: 1.5mm; border: 1px dashed #d97706; border-radius: 2mm;">
            <span class="label" style="color: #b45309; font-size: 11px;">ملاحظات الزبون:</span>
            <span style="font-weight: 700; margin-top: 0.5mm; font-size: 11.5px;">{{ $order->customer_note }}</span>
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
                    <td class="text-left" dir="ltr">{{ (int) round((float) $item->subtotal) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="totals">
            @if((float) $order->discount_amount > 0)
            <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 11px; margin-bottom: 0.8mm; color: #444;">
                <span>المجموع الفرعي:</span>
                <span dir="ltr">{{ (int) round((float) $order->subtotal) }} {{ $restaurant->currency }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 11px; color: #b91c1c; margin-bottom: 0.8mm;">
                <span>قيمة الحسم:</span>
                <span dir="ltr">-{{ (int) round((float) $order->discount_amount) }} {{ $restaurant->currency }}</span>
            </div>
            @endif
            <div class="total-row" style="{{ (float) $order->discount_amount > 0 ? 'border-top: 1px dashed #666; padding-top: 1mm; margin-top: 0.8mm;' : '' }}">
                <span>الإجمالي:</span>
                <span dir="ltr">{{ (int) round((float) $order->total) }} {{ $restaurant->currency }}</span>
            </div>
        </div>

        @if(!empty($wheelQrBase64) && $order->status->value !== 'cancelled')
        <div class="divider"></div>
        <div style="text-align: center; margin: 1.5mm 0; padding: 1.5mm 1mm; border: 1px dashed #000; border-radius: 3px; background: #fafafa;">
            <div style="font-weight: 800; font-size: 11.5px; margin-bottom: 0.5mm;">🎁 امسح لتدوير عجلة الحظ واربح خصم!</div>
            <img src="data:image/png;base64,{{ $wheelQrBase64 }}" style="width: 22mm; height: 22mm; margin: 1mm auto; display: block;" alt="رمز عجلة الحظ" />
            <div style="font-size: 9px; font-weight: 700; color: #444;">كود فريد لمرة واحدة • صالح لـ 14 يوم</div>
        </div>
        @endif

        <div class="divider"></div>

        <div class="footer">
            <div>شكراً لزيارتكم! نتمنى لكم يوماً سعيداً.</div>
            <div style="margin-top: 1mm; font-size: 10px; font-weight: 700; color: #555; text-align: center; letter-spacing: 0.5px;">
                Powered by: www.tedallal.tech
            </div>
        </div>
    </div>

</body>

</html>