# -*- coding: utf-8 -*-
"""
Generate a high-end, professional PDF for Tedallal Field Sales & Marketing Playbook
Designed specifically as an official handover guide for field sales representatives.
Features:
- Official Golden Tedallal Emblem in all headers (replacing letter 'ت')
- Rep-centric empowering tone (addressing the sales rep directly)
- Crystal-clear incentive and commission breakdown
- Beautiful Cairo typography, RTL, 3 pages, vector output via Edge Chromium
"""

import os
import subprocess
import shutil
import base64

# 1. Load golden logo as base64
logo_path = os.path.abspath("public/images/tedallal-golden-crop.png")
if not os.path.exists(logo_path):
    logo_path = os.path.abspath("public/images/tedallal-golden-logo-pure.png")

with open(logo_path, "rb") as f:
    logo_b64 = base64.b64encode(f.read()).decode("utf-8")

logo_html = f'''<div class="brand-logo-container">
    <img src="data:image/png;base64,{logo_b64}" alt="شعار تدلّل" class="brand-logo-img">
</div>'''

html_content = f"""<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حقيبة مندوب المبيعات الميداني المعتمد — منصة تدلّل تِك</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {{
            size: A4;
            margin: 15mm 15mm 18mm 15mm;
            @bottom-center {{
                content: "صفحة " counter(page);
                font-family: 'Cairo', sans-serif;
                font-size: 9pt;
                color: #64748b;
            }}
        }}
        
        * {{
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }}

        body {{
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            background-color: #ffffff;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.65;
            font-size: 10pt;
            direction: rtl;
            text-align: right;
        }}

        /* Document Header */
        .doc-header {{
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 14px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }}

        .brand-logo {{
            display: flex;
            align-items: center;
            gap: 12px;
        }}

        .brand-logo-container {{
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border-radius: 10px;
            padding: 3px 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }}

        .brand-logo-img {{
            height: 44px;
            width: auto;
            display: block;
            object-fit: contain;
        }}

        .brand-text h1 {{
            margin: 0;
            font-size: 16pt;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }}

        .brand-text span.tech {{
            color: #d97706;
            font-size: 11pt;
            font-weight: 700;
            margin-right: 4px;
        }}

        .brand-text p {{
            margin: 2px 0 0 0;
            font-size: 8pt;
            color: #64748b;
            font-weight: 600;
        }}

        .doc-badge {{
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 700;
            color: #334155;
            text-align: left;
        }}

        .doc-badge .date {{
            color: #b45309;
        }}

        /* Hero Title Banner */
        .hero-banner {{
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 20px 24px;
            border-radius: 14px;
            margin-bottom: 22px;
            position: relative;
            border-right: 6px solid #f59e0b;
        }}

        .hero-banner h2 {{
            margin: 0 0 8px 0;
            font-size: 14.5pt;
            font-weight: 900;
            color: #f8fafc;
        }}

        .hero-banner p {{
            margin: 0;
            font-size: 9pt;
            color: #cbd5e1;
            line-height: 1.5;
        }}

        /* Section Styling */
        .section {{
            margin-bottom: 20px;
            page-break-inside: avoid;
        }}

        .section-title {{
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11.5pt;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }}

        .section-icon {{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background-color: #fef3c7;
            color: #b45309;
            border-radius: 6px;
            font-size: 9pt;
            font-weight: 900;
        }}

        /* Cards & Highlight Boxes */
        .card {{
            border-radius: 12px;
            padding: 13px 16px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }}

        .card-gold {{
            background-color: #fffbeb;
            border-color: #fcd34d;
        }}

        .card-blue {{
            background-color: #eff6ff;
            border-color: #bfdbfe;
        }}

        .card-emerald {{
            background-color: #ecfdf5;
            border-color: #a7f3d0;
        }}

        .card-title {{
            font-size: 9.5pt;
            font-weight: 800;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }}

        .card-gold .card-title {{ color: #92400e; }}
        .card-blue .card-title {{ color: #1e40af; }}
        .card-emerald .card-title {{ color: #065f46; }}

        /* Step Process Flow */
        .flow-container {{
            display: flex;
            gap: 10px;
            margin: 10px 0 14px 0;
        }}

        .flow-step {{
            flex: 1;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            position: relative;
        }}

        .flow-step-num {{
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 12px;
            margin-bottom: 6px;
        }}

        .flow-step h4 {{
            margin: 0 0 4px 0;
            font-size: 9.5pt;
            font-weight: 800;
            color: #0f172a;
        }}

        .flow-step p {{
            margin: 0;
            font-size: 8pt;
            color: #475569;
            line-height: 1.4;
        }}

        /* Lists */
        .step-list {{
            list-style: none;
            padding: 0;
            margin: 0;
        }}

        .step-item {{
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 9pt;
        }}

        .step-num {{
            min-width: 22px;
            height: 22px;
            background-color: #f59e0b;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
            font-weight: 800;
            margin-top: 2px;
        }}

        .step-content {{
            flex: 1;
            color: #334155;
        }}

        /* Speech Pitch Box */
        .pitch-box {{
            background-color: #f1f5f9;
            border-right: 4px solid #0284c7;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.6;
            margin: 8px 0;
            font-style: italic;
        }}

        /* Comparison Table */
        table {{
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8.5pt;
        }}

        th, td {{
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            text-align: right;
        }}

        th {{
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 8.5pt;
        }}

        tr:nth-child(even) {{
            background-color: #f8fafc;
        }}

        .objection-td {{
            font-weight: 700;
            color: #991b1b;
            background-color: #fef2f2;
        }}

        .response-td {{
            color: #065f46;
            background-color: #f0fdf4;
            font-weight: 600;
        }}

        /* Badges */
        .badge {{
            display: inline-block;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 7.5pt;
            font-weight: 700;
        }}
        .badge-green {{ background: #dcfce7; color: #166534; }}
        .badge-amber {{ background: #fef3c7; color: #92400e; }}
        .badge-purple {{ background: #f3e8ff; color: #6b21a8; }}

        /* Footer */
        .footer {{
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #94a3b8;
        }}

        .page-break {{
            page-break-after: always;
        }}
    </style>
</head>
<body>

    <!-- ============================================================== -->
    <!-- PAGE 1: STRATEGY, PRE-MADE SAMPLE & SMART APPROACH -->
    <!-- ============================================================== -->

    <!-- Header 1 -->
    <header class="doc-header">
        <div class="brand-logo">
            {logo_html}
            <div class="brand-text">
                <h1>تدلّـل <span class="tech">تِك (Tedallal.tech)</span></h1>
                <p>الأنظمة السحابية الذكية لإدارة وتشغيل المطاعم والكافيهات</p>
            </div>
        </div>
        <div class="doc-badge">
            <div>حقيبة مندوب المبيعات المعتمد</div>
            <div class="date">وثيقة رسمية لفريق المبيعات الميدانية</div>
        </div>
    </header>

    <!-- Hero Title Banner -->
    <div class="hero-banner">
        <h2>دليل المبيعات الميداني واكتساب أول عملاء لمنصة تدلّل تِك</h2>
        <p>حقيبتك التدريبية والعملية في الميدان: كيف تحوّل كل زيارة لمطعم إلى صفقة اشتراك مؤكدة، وهندسة الإقناع الذكي لأصحاب المطاعم والكافيهات.</p>
    </div>

    <!-- 1. The Strategy Philosophy -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">1</span>
            <span>فلسفة النجاح: استراتيجية «العميل الشريك المؤسس» (Lighthouse Customer)</span>
        </div>
        <p style="margin: 0 0 10px 0;">
            أنت كرجل مبيعات شريك في نجاح تدلّل، تذكر دائماً: أصحاب المطاعم في عالمنا العربي لا يشترون برمجيات معقدة؛ بل يشترون <strong>رؤية النتائج الملموسة والحل السريع لمشاكلهم اليومية</strong>. كسب أول مطعم في منطقتك كـ «شريك مؤسس» يفتح أمامك أبواب كافة المطاعم المجاورة بأقل مجهود!
        </p>

        <div class="flow-container">
            <div class="flow-step">
                <span class="flow-step-num">خطوة 1</span>
                <h4>اختيار مطعم شريك</h4>
                <p>مقهى أو مطعم وجبات حركته نشطة ويديره مالك متواجد</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">خطوة 2</span>
                <h4>تشغيل تجريبي مجاني</h4>
                <p>30 يوماً بدون مخاطرة مع تجهيز المنيو والأستاندات هدية</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">خطوة 3</span>
                <h4>توثيق قصة النجاح</h4>
                <p>استخراج تقرير أرباح والتقاط فيديو شهادة رضاء من المالك</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">خطوة 4</span>
                <h4>التوسع السريع</h4>
                <p>تثبيت اشتراكه واستقطاب باقي مطاعم المنطقة بثقة تامة</p>
            </div>
        </div>
    </div>

    <!-- 2. Target Profile -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">2</span>
            <span>هدفك الذهبي: من هم أول العملاء الذين تبدأ بهم؟</span>
        </div>
        <div class="card card-blue">
            <div class="card-title">🎯 المواصفات الدقيقة للمطعم الأنسب لتحقيق إغلاق سريع ومضمون:</div>
            <ul style="margin: 0; padding-right: 20px; font-size: 9pt;">
                <li><strong>المطاعم العصرية، الكافيهات، ومحلات البرغر والشاورما السريعة:</strong> حيث حركة الزبائن سريعة وتكلفة وقت الويتر حرجة جداً.</li>
                <li><strong>المدير أو صاحب المطعم متواجد بنفسه في الصالة:</strong> تجنب السلاسل العملاقة البيروقراطية التي تتطلب لجان مشتريات وموافقات طويلة.</li>
                <li><strong>يعاني يومياً من مشكلتين رئيسيتين:</strong> تأخر أخذ الطلبات في ساعات الذروة، وأخطاء النقل الشفهي والورقي للمطبخ.</li>
            </ul>
        </div>
    </div>

    <!-- 3. Pre-made Prototype Hook -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">3</span>
            <span>حيلتك الأقوى: «العينة الجاهزة المسبقة» (The Pre-made Prototype Hook)</span>
        </div>
        
        <div class="card card-gold">
            <div class="card-title">⚠️ قاعدتك الذهبية كمندوب محترف: إياك والدخول بأسلوب البائع التقليدي!</div>
            <p style="margin: 0; font-size: 8.5pt;">
                إذا دخلت وقلت: <em>"أنا مندوب مبيعات وجاي أبيعك برنامج..."</em>، ستُقابل بالرفض الفوري المعتاد: <em>"مش فاضيين يا غالي!"</em>.
            </p>
        </div>

        <p style="margin: 8px 0; font-size: 9pt;"><strong>خطتك للدخول الذكي (تحضير 5 دقائق فقط من هاتفك قبل الزيارة):</strong></p>
        <ol class="step-list">
            <li class="step-item">
                <span class="step-num">1</span>
                <div class="step-content">ادخل صفحة المطعم على إنستغرام أو فيسبوك، واحفظ شعاره و 5 أطباق من منيو المطعم مع أسعارها.</div>
            </li>
            <li class="step-item">
                <span class="step-num">2</span>
                <div class="step-content">أنشئ حساباً تجريبياً فورياً على تدلّل باسم المطعم وجهّز المنيو ورمز الـ QR خلال 5 دقائق.</div>
            </li>
            <li class="step-item">
                <span class="step-num">3</span>
                <div class="step-content">ادخل المطعم، اطلب قهوة كزبون عادي، وعندما يفرغ المدير أو صاحب المطعم، اقترب منه بابتسامة:</div>
            </li>
        </ol>

        <div class="pitch-box">
            "السلام عليكم يا معلم.. القهوة ما شاء الله ممتازة والأجواء مرتبة الله يباركلك.<br>
            أنا اسمي (فلان) من منصة «تدلّل تِك» لحلول المطاعم.. بصراحة مطعمكم من أرقى الأماكن بالمنطقة وحبيت أعملك مفاجأة بسيطة:<br>
            خذ هاتفك وافتح الكاميرا على هالباركود.. شوف منيو مطعمك كيف صار شكله الذكي بلحظة وبدون أي تطبيق، ومع ويتر ذكي برد على زباينك ويقترح أطباقك!"
        </div>
    </div>

    <!-- Page Break for Next Page -->
    <div class="page-break"></div>

    <!-- ============================================================== -->
    <!-- PAGE 2: 3-MINUTE DEMO, OBJECTIONS & CLOSING OFFER -->
    <!-- ============================================================== -->

    <!-- Header 2 -->
    <header class="doc-header">
        <div class="brand-logo">
            {logo_html}
            <div class="brand-text">
                <h1>تدلّـل <span class="tech">تِك (Tedallal.tech)</span></h1>
                <p>دليل الإقناع والمبيعات الميدانية — الصفحة 2</p>
            </div>
        </div>
        <div class="doc-badge">
            <div>خطوات العرض ومعالجة الاعتراضات</div>
        </div>
    </header>

    <!-- 4. The 3-Minute Live Demo -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">4</span>
            <span>عرضك الميداني الحي التفاعلي في 3 دقائق (The 3-Minute Live Demo)</span>
        </div>
        <p style="margin: 0 0 8px 0;">بمجرد أن يمسح صاحب المطعم الرمز ويرى وجباته الخاصة معروضة بهوية أنيقة، يولد لديه <strong>حس التملك (Psychological Ownership)</strong>. استغل اللحظة ونفّذ معه التجربة الحية فوراً:</p>

        <div class="flow-container">
            <div class="flow-step">
                <span class="flow-step-num">1</span>
                <h4>الطلب من الطاولة</h4>
                <p>صاحب المطعم يختار طبقاً من هاتفه ويكتب ملاحظة ويضغط إرسال</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">2</span>
                <h4>رنين فوري للكاشير</h4>
                <p>يهتز هاتفك فوراً كشاشة كاشير مع نغمة التنبيه الصوتي الحية</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">3</span>
                <h4>شاشة المطبخ KDS</h4>
                <p>يظهر الطلب فوراً على شاشة الشيف بأقسام الطهي وتوقيت التحضير</p>
            </div>
        </div>

        <div class="card card-emerald">
            <div class="card-title">💡 حجج الإقناع الذهبية الثلاث التي تذكرها بثقة لصاحب المطعم:</div>
            <div style="font-size: 8.5pt; line-height: 1.5;">
                <strong>1. تسريع دوران الطاولات (Turnover):</strong> طاولتك بدل ما تخدم زبون كل 45 دقيقة بالانتظار، بتخدمه بـ 30 دقيقة، يعني 30% مبيعات إضافية بنفس المكان.<br>
                <strong>2. صفر أخطاء بالمطبخ:</strong> مفيش طلب بيضيع، ولا صنف بينزل ناقص، ولا زبون بيشتكي إن وجبته بردت.<br>
                <strong>3. زيادة الفاتورة بالويتر الذكي (AI Upselling):</strong> الويتر الذكي يقترح صوص، مقبلات، ومشروب مع كل طبق، ليرفع متوسط الفاتورة بنسبة 18% - 25% تلقائياً.
            </div>
        </div>
    </div>

    <!-- 5. Objection Handling Table -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">5</span>
            <span>سلاحك في الميدان: دليل الردود الذكية على اعتراضات أصحاب المطاعم</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 32%;">اعتراض صاحب المطعم المعتاد</th>
                    <th>ردك الذكي والمقنع والمطمئن له</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="objection-td">"عندي ويترية كافيين وما في داعي للبرامج والتكنولوجيا"</td>
                    <td class="response-td">"الله يباركلهم، النظام مش جاي يستغني عنهم، جاي يساعدهم! الويتر بدل ما يركض 10 مرات مشان يجيب منيو ويسجل بورقة، بصير تركيزه كله على الترحيب بالزبائن ونظافة الصالة وسرعة التقديم."</td>
                </tr>
                <tr>
                    <td class="objection-td">"الزبائن عنا ما بيعرفوا يمسحوا باركود أو كبار بالعمر"</td>
                    <td class="response-td">"تدلّل يدعم الطريقتين معاً: اللي بحب يمسح الباركود بنبسط جداً بالسرعة، واللي ما بحب، الويتر بسجل طلبه من نفس النظام بثانية. بالمقابل كسبت فئة الشباب اللي بتعشق السرعة."</td>
                </tr>
                <tr>
                    <td class="objection-td">"الإنترنت أحياناً بكون ضعيف أو مقطوع عنا"</td>
                    <td class="response-td">"تدلّل مبرمج بتقنية PWA فائقة الخفة بدون تحميل أي تطبيق، يفتح فوراً حتى على شبكة 3G ضعيفة جداً، ويشتغل محلياً داخل الصالة بدون أي استهلاك للبيانات."</td>
                </tr>
                <tr>
                    <td class="objection-td">"البرامج بتعلق وبدها أجهزة كاشير وكمبيوترات غالية"</td>
                    <td class="response-td">"هنا ميزة تدلّل الحقيقية: ما بدنا منك تشتري ولا شاشة ولا كمبيوتر خاص! أي موبايل، أو تابلت، أو لابتوب مستعمل عندك بالمطعم بشتغل عليه النظام فوراً بنقرة زر."</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 6. The Closing Offer -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">6</span>
            <span>عرض الإغلاق الذي لا يُرفض (The Irresistible Pilot Offer)</span>
        </div>

        <div class="pitch-box" style="border: 2px solid #f59e0b; background-color: #fffbeb;">
            "معلم، نحن ما جايين نطلب منك ولا قرش اليوم. نحن فخورين بمطعمك وسمعته بالمنطقة، وحابين نختارك أنت حصرياً كـ <strong>«الشريك المؤسس الأول للمنصة في منطقتنا»</strong>:<br>
            1. حنجهّزلك المنيو كامل بأسماء وجباتك وأسعارها وصورها مجاناً بالكامل.<br>
            2. حنطبعلك أستاندات طاولات فاخرة بالباركود وشعار مطعمك هدية مجانية منا.<br>
            3. حنعطيك النظام شغال بكامل باقة Pro المتقدمة تجربة حية مجانية لمدة 30 يوماً كاملة.<br>
            4. إذا بعد الـ 30 يوم ارتحت وشفت المبيعات زادت وأخطاء الصالة اختفت، منثبتلك اشتراكك بخصم دائم 50% مدى الحياة تكريماً لأنك شريكنا الأول.. وإذا ما عجبك، بنكون كسبنا معرفتك وتشرفنا بخدمتك بدون أي التزام مالي عليك!"
        </div>
    </div>

    <!-- Page Break for Next Page -->
    <div class="page-break"></div>

    <!-- ============================================================== -->
    <!-- PAGE 3: PRICING MATRIX, INCENTIVES & ONBOARDING -->
    <!-- ============================================================== -->

    <!-- Header 3 -->
    <header class="doc-header">
        <div class="brand-logo">
            {logo_html}
            <div class="brand-text">
                <h1>تدلّـل <span class="tech">تِك (Tedallal.tech)</span></h1>
                <p>دليل الباقات ونظام العمولات — الصفحة 3</p>
            </div>
        </div>
        <div class="doc-badge">
            <div>مصفوفة الباقات وخطة الأرباح والمتابعة</div>
        </div>
    </header>

    <!-- 7. Pricing Matrix Reference -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">7</span>
            <span>مصفوفة الباقات المعتمدة لتسويقها للمطاعم (Basic, Pro, Max)</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>الباقة والمستوى</th>
                    <th>السعر المعتمد (الخليج/دولي)</th>
                    <th>السعر المعتمد (السوق المحلي)</th>
                    <th>الميزات التشغيلية الرئيسية</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>الأساسية (Basic)</strong><br><span class="badge badge-amber">إدارة الطلب</span></td>
                    <td><strong>$29 / شهرياً</strong><br>(109 ريال سعودي)</td>
                    <td><strong>$15 / شهرياً</strong><br>(~ 225,000 ل.س)</td>
                    <td>منيو QR غير محدود الأصناف، حتى 15 طاولة، شاشة كاشير سحابية، شاشة مطبخ واحدة، تقارير أساسية.</td>
                </tr>
                <tr>
                    <td><strong>المتقدمة (Pro) 🏆</strong><br><span class="badge badge-green">الأكثر طلباً</span></td>
                    <td><strong>$59 / شهرياً</strong><br>(219 ريال سعودي)</td>
                    <td><strong>$29 / شهرياً</strong><br>(~ 435,000 ل.س)</td>
                    <td>طاولات غير محدودة، شاشات مطبخ متعددة (Multi-KDS)، <strong>نظام الكوبونات والعروض + عجلة الحظ</strong>، تحليلات متقدمة.</td>
                </tr>
                <tr>
                    <td><strong>الشاملة (Max) 🤖</strong><br><span class="badge badge-purple">ذكاء اصطناعي</span></td>
                    <td><strong>$99 / شهرياً</strong><br>(369 ريال سعودي)</td>
                    <td><strong>$49 / شهرياً</strong><br>(~ 735,000 ل.س)</td>
                    <td>كل ميزات Pro + <strong>الويتر الذكي (AI Waiter)</strong> لزيادة المبيعات + هوية بصرية مخصصة للمطعم بالكامل (White-label) + دعم VIP.</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size: 8pt; color: #64748b; margin-top: 4px;">
            * ميّز عميلك بعرض خصم 20% (شهران مجاناً) عند الاشتراك السنوي مقدماً.
        </p>
    </div>

    <!-- 8. Sales Rep Compensation -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">8</span>
            <span>نظام الحوافز والأرباح المباشرة لك كمندوب مبيعات شريك</span>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
            <div class="card card-emerald" style="flex: 1; margin: 0;">
                <div class="card-title">💰 عمولتك الفورية (100% من أول شهر):</div>
                <p style="margin: 0; font-size: 8.5pt; line-height: 1.5;">
                    تحصل فوراً على <strong>قيمة اشتراك الشهر الأول بالكامل</strong> عن كل مطعم جديد يوقّع معك (مثلاً: 29$ فورية عند اشتراكه في باقة Pro، أو 49$ لباقة Max). كل صفقة تنجزها، عوائدها الأولى لك تقديراً لجهدك الميداني!
                </p>
            </div>
            <div class="card card-blue" style="flex: 1; margin: 0;">
                <div class="card-title">🔄 دخلك الشهري التراكمي المستمر (15% شهرياً):</div>
                <p style="margin: 0; font-size: 8.5pt; line-height: 1.5;">
                    تحصل على <strong>15% من قيمة كل تجديد شهري</strong> يدفعه المطعم مستقبلاً. بمجرد بناء محفظة من 20 إلى 30 مطعماً، يتشكّل لديك دخل شهري متنامٍ ومستمر يستمر معك طالما المطعم مستمر في الخدمة.
                </p>
            </div>
        </div>
    </div>

    <!-- 9. Week 1 Onboarding Roadmap -->
    <div class="section">
        <div class="section-title">
            <span class="section-icon">9</span>
            <span>خطة أسبوعك الأول مع المطعم الجديد لضمان استمراره ورضاه (Onboarding Roadmap)</span>
        </div>

        <div class="flow-container">
            <div class="flow-step">
                <span class="flow-step-num">اليوم 1</span>
                <h4>إدخال القائمة</h4>
                <p>مراجعة الأصناف والأسعار مع المالك ورفع الصور بدقة</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">اليوم 2</span>
                <h4>تدريب الكادر</h4>
                <p>جلسة 20 دقيقة لشيف المطبخ والكاشير على استخدام الشاشات</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">اليوم 3</span>
                <h4>الإطلاق الحي</h4>
                <p>تواجدك في الصالة وقت الذروة لمساندة الطاقم والزبائن</p>
            </div>
            <div class="flow-step">
                <span class="flow-step-num">اليوم 7</span>
                <h4>مراجعة وتوثيق</h4>
                <p>استخراج تقرير أرباح والتقاط فيديو شهادة رضاء من المالك</p>
            </div>
        </div>
    </div>

    <!-- Final Encouragement Note -->
    <div class="card card-gold" style="margin-top: 15px; text-align: center;">
        <strong style="color: #92400e; font-size: 10pt;">🚀 شعارك في الميدان كأحد قادة مبيعات تدلّل تِك:</strong>
        <p style="margin: 4px 0 0 0; font-size: 9pt; color: #78350f;">
            "نحن لا نبيع برنامجاً، بل نبيع راحة بال لصاحب المطعم، وسرعة طاولات تضاعف دخله اليومي، وتجربة ذكية تجعل زبائنه يعشقون المكان!"
        </p>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div>منصة تدلّل تِك — جميع الحقوق محفوظة © 2026 | Tedallal.tech</div>
        <div>الحقيبة المعتمدة لمندوبي المبيعات الميدانية</div>
    </footer>

</body>
</html>
"""

# 2. Write HTML file
output_html_path = os.path.abspath("tedallal_sales_guide.html")
with open(output_html_path, "w", encoding="utf-8") as f:
    f.write(html_content)

print(f"HTML generated at: {output_html_path}")

# 3. Render to PDF using Microsoft Edge headless
edge_path = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
if not os.path.exists(edge_path):
    edge_path = r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"

output_pdf_workspace = os.path.abspath("public/docs/tedallal_sales_rep_guide.pdf")
os.makedirs(os.path.dirname(output_pdf_workspace), exist_ok=True)

root_pdf_new = os.path.abspath("حقيبة_مندوب_المبيعات_الميداني_تدلل_تك.pdf")
old_root_pdf = os.path.abspath("دليل_المبيعات_والتسويق_تدلل_تك.pdf")
old_public_pdf = os.path.abspath("public/docs/tedallal_sales_guide.pdf")

artifact_dir = r"C:\Users\ASUS\.gemini\antigravity\brain\8b817e4d-e32a-49c8-a0f9-9f3b7546d63d"
artifact_pdf = os.path.join(artifact_dir, "حقيبة_مندوب_المبيعات_الميداني_تدلل_تك.pdf")

args = [
    edge_path,
    "--headless",
    "--disable-gpu",
    "--run-all-compositor-stages-before-draw",
    f"--print-to-pdf={output_pdf_workspace}",
    "--no-pdf-header-footer",
    f"file:///{output_html_path.replace(os.sep, '/')}"
]

print("Rendering PDF via Microsoft Edge...")
subprocess.run(args, check=True)

if os.path.exists(output_pdf_workspace):
    shutil.copy2(output_pdf_workspace, root_pdf_new)
    shutil.copy2(output_pdf_workspace, artifact_pdf)
    print(f"SUCCESS: New PDF generated at:\n1. {output_pdf_workspace}\n2. {root_pdf_new}\n3. {artifact_pdf}")
    
    # Try updating old paths if not locked
    for old_path in [old_public_pdf, old_root_pdf]:
        try:
            shutil.copy2(output_pdf_workspace, old_path)
            print(f"Also updated locked path: {old_path}")
        except Exception:
            print(f"Note: {old_path} is currently open in your PDF viewer, so new version is saved at: {root_pdf_new}")
else:
    print("ERROR: PDF was not generated.")

