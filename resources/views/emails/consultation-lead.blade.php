<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استمارة تقييم ما قبل البيع - {{ $lead->restaurant_name }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; direction: rtl; text-align: right; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 650px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 35px 30px; text-align: center; color: #ffffff;">
                            <div style="display: inline-block; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 30px; padding: 6px 16px; margin-bottom: 12px;">
                                <span style="color: #f59e0b; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">⚡ استمارة تقييم ما قبل البيع • عميل محتمل جديد</span>
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #ffffff;">
                                متطلبات مطعم: {{ $lead->restaurant_name }}
                            </h1>
                            <p style="margin: 8px 0 0; color: #94a3b8; font-size: 14px;">
                                تم التقديم بتاريخ {{ $lead->created_at ? $lead->created_at->format('Y-m-d h:i A') : now()->format('Y-m-d h:i A') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Client Summary Card -->
                    <tr>
                        <td style="padding: 25px 30px 15px;">
                            <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 14px; padding: 20px;">
                                <table width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <span style="font-size: 13px; color: #92400e; font-weight: bold;">اسم المسؤول / المالك:</span>
                                            <strong style="font-size: 16px; color: #1e293b; display: block; margin-top: 3px;">{{ $lead->name }}</strong>
                                        </td>
                                        <td style="padding-bottom: 10px;">
                                            <span style="font-size: 13px; color: #92400e; font-weight: bold;">اسم المطعم:</span>
                                            <strong style="font-size: 16px; color: #1e293b; display: block; margin-top: 3px;">{{ $lead->restaurant_name }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 10px;">
                                            <span style="font-size: 13px; color: #92400e; font-weight: bold;">رقم الهاتف / الواتساب:</span>
                                            <div style="margin-top: 4px;">
                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->phone) }}" target="_blank" style="display: inline-block; background-color: #25d366; color: #ffffff; text-decoration: none; padding: 7px 16px; border-radius: 8px; font-weight: bold; font-size: 14px; direction: ltr;">
                                                    💬 {{ $lead->phone }}
                                                </a>
                                            </div>
                                        </td>
                                        <td style="padding-top: 10px;">
                                            <span style="font-size: 13px; color: #92400e; font-weight: bold;">المدينة:</span>
                                            <strong style="font-size: 15px; color: #1e293b; display: block; margin-top: 3px;">{{ $lead->city ?: 'غير محدد' }}</strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Assessment Answers -->
                    <tr>
                        <td style="padding: 10px 30px;">
                            <h3 style="margin: 0 0 12px; font-size: 16px; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;">
                                🏢 طبيعة العمل والتشغيل
                            </h3>
                            <table width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; line-height: 1.8;">
                                <tr>
                                    <td width="35%" style="color: #64748b; font-weight: 600; padding: 6px 0;">أسلوب خدمة الزبائن:</td>
                                    <td width="65%" style="color: #0f172a; font-weight: bold; padding: 6px 0;">{{ $lead->service_type }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; font-weight: 600; padding: 6px 0;">آلية المحاسبة عند الكاشير:</td>
                                    <td style="color: #0f172a; font-weight: bold; padding: 6px 0;">{{ $lead->payment_model }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; font-weight: 600; padding: 6px 0;">توزيع شاشات المطبخ:</td>
                                    <td style="color: #0f172a; font-weight: bold; padding: 6px 0;">{{ $lead->kitchen_setup }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Section: Desired Features -->
                    <tr>
                        <td style="padding: 15px 30px 10px;">
                            <h3 style="margin: 0 0 12px; font-size: 16px; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;">
                                🚀 الميزات المطلوبة في تدلّل
                            </h3>
                            <div style="margin-top: 8px;">
                                @foreach((array)$lead->desired_features as $f)
                                    <span style="display: inline-block; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: bold; margin-left: 6px; margin-bottom: 6px;">
                                        ★ {{ $f }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                    </tr>

                    <!-- Notes -->
                    @if($lead->notes)
                    <tr>
                        <td style="padding: 10px 30px 20px;">
                            <h3 style="margin: 0 0 8px; font-size: 15px; color: #0f172a;">ملاحظات العميل:</h3>
                            <div style="background: #f1f5f9; border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #475569; line-height: 1.6;">
                                {{ $lead->notes }}
                            </div>
                        </td>
                    </tr>
                    @endif

                    <!-- WhatsApp Direct Contact Button -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->phone) }}?text={{ urlencode('مرحباً أستاذ ' . $lead->name . '، معك عمر خطيب من نظام تدلّل لإدارة المطاعم الذكية. اطلعت على استمارة متطلبات مطعم ' . $lead->restaurant_name . ' وسعيد جداً بالتواصل معك لترتيب العرض التوضيحي وتفعيل النسخة التجريبية.') }}" target="_blank" style="display: inline-block; background-color: #f59e0b; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 12px; font-weight: 800; font-size: 15px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                                📲 فتح محادثة فورية مع العميل على واتساب
                            </a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
