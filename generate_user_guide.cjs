/**
 * Tedallal Comprehensive User Guide & System Specifications Generator
 * Generates an executive, publication-grade PDF manual for Tedallal Intelligent Restaurant Ecosystem.
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

console.log('>>> [1/5] Loading and converting platform media assets to Base64...');
const imgDir = path.join(__dirname, 'public', 'images');

function toBase64(filename, mime = 'image/jpeg') {
  const p = path.join(imgDir, filename);
  if (!fs.existsSync(p)) {
    console.warn(`Warning: Image ${filename} not found.`);
    return '';
  }
  const ext = path.extname(filename).toLowerCase();
  const detectedMime = ext === '.png' ? 'image/png' : ext === '.svg' ? 'image/svg+xml' : 'image/jpeg';
  const data = fs.readFileSync(p);
  return `data:${detectedMime};base64,${data.toString('base64')}`;
}

const LOGO_GOLD = toBase64('tedallal-golden-logo-pure.png', 'image/png');
const BRAND_LOGO = toBase64('tedallal-brand-logo.jpg', 'image/jpeg');
const QR_PHONE = toBase64('tedallal-qr-phone-mockup.jpg', 'image/jpeg');
const SAAS_DASHBOARD = toBase64('tedallal-luxury-saas-mockup.jpg', 'image/jpeg');
const KDS_TABLET = toBase64('tedallal-kds-tablet-mockup.jpg', 'image/jpeg');
const FLOOR_PLAN = toBase64('tedallal-floor-plan-mockup.jpg', 'image/jpeg');
const ANALYTICS_CHART = toBase64('tedallal-analytics-chart-mockup.jpg', 'image/jpeg');
const CHEF_HERO = toBase64('tedallal-chef-hero.jpg', 'image/jpeg');
const TECH_QR = toBase64('tedallal-tech-qr.png', 'image/png');

console.log('>>> [2/5] Compiling HTML & Styling for Executive PDF User Guide...');

const html = `<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>دليل الاستخدام الشامل لمنصة تدلّل للمطاعم الذكية</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<style>
  @page {
    size: A4;
    margin: 14mm 16mm 16mm 16mm;
  }

  *, *::before, *::after {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  body {
    font-family: 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    color: #1e293b;
    background: #ffffff;
    margin: 0;
    padding: 0;
    line-height: 1.65;
    font-size: 13.5px;
  }

  /* Page Break Utilities */
  .page-break {
    page-break-before: always;
    break-before: page;
  }

  .avoid-break {
    page-break-inside: avoid;
    break-inside: avoid;
  }

  /* Cover Page */
  .cover-page {
    background: linear-gradient(135deg, #090d16 0%, #0f172a 60%, #1a2238 100%);
    color: #ffffff;
    padding: 60px 45px;
    height: 100%;
    min-height: 1020px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    page-break-after: always;
    break-after: page;
  }

  .cover-decor {
    position: absolute;
    top: -120px;
    left: -120px;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(217, 119, 6, 0.22) 0%, rgba(0,0,0,0) 70%);
    border-radius: 50%;
  }

  .cover-decor-bottom {
    position: absolute;
    bottom: -150px;
    right: -150px;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(0,0,0,0) 70%);
    border-radius: 50%;
  }

  .cover-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    padding-bottom: 24px;
  }

  .cover-logo-img {
    height: 68px;
    filter: drop-shadow(0 4px 12px rgba(217, 119, 6, 0.35));
  }

  .cover-badge {
    background: rgba(217, 119, 6, 0.15);
    border: 1px solid rgba(245, 158, 11, 0.4);
    color: #fcd34d;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
  }

  .cover-body {
    position: relative;
    z-index: 2;
    margin: 60px 0;
  }

  .cover-title-label {
    color: #d97706;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: block;
  }

  .cover-main-title {
    font-size: 40px;
    font-weight: 900;
    line-height: 1.25;
    margin: 0 0 18px 0;
    color: #ffffff;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
  }

  .cover-main-title span {
    color: #f59e0b;
    background: linear-gradient(90deg, #fbbf24, #d97706);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .cover-subtitle {
    font-size: 17px;
    color: #cbd5e1;
    font-weight: 500;
    max-width: 650px;
    line-height: 1.6;
    margin-bottom: 30px;
  }

  .cover-highlights-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 35px;
  }

  .cover-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 16px 18px;
  }

  .cover-card-icon {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
  }

  .cover-card-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #f8fafc;
    margin-bottom: 4px;
  }

  .cover-card-desc {
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.45;
  }

  .cover-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    position: relative;
    z-index: 2;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    padding-top: 24px;
  }

  .cover-meta-item {
    font-size: 11.5px;
    color: #94a3b8;
    line-height: 1.6;
  }

  .cover-meta-item strong {
    color: #f1f5f9;
  }

  /* Running Header & Footer for Chapters */
  .chapter-header {
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 12px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .chapter-brand-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #475569;
    font-weight: 700;
  }

  .chapter-brand-pill img {
    height: 22px;
  }

  .chapter-num-badge {
    background: #0f172a;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 6px;
  }

  /* Typography */
  h1.chapter-title {
    font-size: 26px;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  h1.chapter-title .num-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #d97706, #b45309);
    color: white;
    border-radius: 8px;
    font-size: 17px;
    font-weight: 900;
  }

  h2 {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 22px 0 10px 0;
    padding-right: 10px;
    border-right: 4px solid #d97706;
  }

  h3 {
    font-size: 14.5px;
    font-weight: 700;
    color: #334155;
    margin: 16px 0 8px 0;
  }

  p {
    margin: 0 0 12px 0;
    color: #334155;
  }

  /* Callout Boxes */
  .callout {
    border-radius: 8px;
    padding: 14px 18px;
    margin: 16px 0;
    display: flex;
    gap: 14px;
    align-items: flex-start;
  }

  .callout-info {
    background: #f0fdf4;
    border-right: 4px solid #10b981;
    color: #065f46;
  }

  .callout-gold {
    background: #fffbeb;
    border-right: 4px solid #f59e0b;
    color: #92400e;
  }

  .callout-blue {
    background: #eff6ff;
    border-right: 4px solid #3b82f6;
    color: #1e40af;
  }

  .callout-alert {
    background: #fef2f2;
    border-right: 4px solid #ef4444;
    color: #991b1b;
  }

  .callout-icon {
    font-size: 20px;
    line-height: 1;
  }

  .callout-content {
    flex: 1;
    font-size: 12.5px;
    line-height: 1.55;
  }

  .callout-content strong {
    display: block;
    font-size: 13.5px;
    margin-bottom: 3px;
  }

  /* Tables */
  table.guide-table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
    font-size: 12.5px;
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
  }

  table.guide-table th {
    background: #0f172a;
    color: #ffffff;
    padding: 10px 14px;
    text-align: right;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.3px;
  }

  table.guide-table td {
    padding: 9px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
  }

  table.guide-table tr:nth-child(even) td {
    background: #f8fafc;
  }

  table.guide-table tr:last-child td {
    border-bottom: none;
  }

  /* Step Process Flow */
  .step-flow {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
  }

  .step-item {
    display: flex;
    gap: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    align-items: center;
  }

  .step-num {
    background: #0f172a;
    color: #f59e0b;
    font-size: 15px;
    font-weight: 900;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .step-content {
    flex: 1;
  }

  .step-content h4 {
    margin: 0 0 3px 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
  }

  .step-content p {
    margin: 0;
    font-size: 12px;
    color: #64748b;
  }

  /* Grid Features */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin: 16px 0;
  }

  .grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin: 16px 0;
  }

  .feature-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
  }

  .feature-box.highlight {
    border-top: 3px solid #d97706;
  }

  .feature-box.success {
    border-top: 3px solid #10b981;
  }

  .feature-box.tech {
    border-top: 3px solid #6366f1;
  }

  .feature-box-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .feature-box-desc {
    font-size: 12px;
    color: #475569;
    line-height: 1.5;
    margin: 0;
  }

  /* Image Containers */
  .mockup-container {
    margin: 18px 0;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    background: #0f172a;
    text-align: center;
  }

  .mockup-container img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
  }

  .mockup-caption {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 8px 14px;
    font-size: 11.5px;
    color: #64748b;
    font-weight: 600;
    text-align: center;
  }

  /* Badge Chips */
  .chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
  }

  .chip-green { background: #dcfce7; color: #15803d; }
  .chip-gold { background: #fef3c7; color: #b45309; }
  .chip-blue { background: #dbeafe; color: #1e40af; }
  .chip-purple { background: #f3e8ff; color: #7e22ce; }
  .chip-red { background: #fee2e2; color: #b91c1c; }

  /* TOC Layout */
  .toc-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 22px 28px;
    margin-top: 20px;
  }

  .toc-list {
    list-style: none;
    padding: 0;
    margin: 0;
    columns: 2;
    column-gap: 30px;
  }

  .toc-item {
    break-inside: avoid;
    margin-bottom: 14px;
    font-size: 13px;
  }

  .toc-item-title {
    font-weight: 700;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    border-bottom: 1px dotted #cbd5e1;
    padding-bottom: 3px;
    margin-bottom: 4px;
  }

  .toc-item-title span.title-text {
    color: #0f172a;
  }

  .toc-item-title span.page-num {
    color: #d97706;
    font-weight: 800;
    direction: ltr;
  }

  .toc-subitems {
    list-style: none;
    padding-right: 12px;
    margin: 0;
    font-size: 11.5px;
    color: #64748b;
  }

  .toc-subitems li {
    margin-bottom: 3px;
  }

  /* Footer Note */
  .guide-footer-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 12mm;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #e2e8f0;
    padding: 0 16mm;
    font-size: 10px;
    color: #94a3b8;
  }
</style>
</head>
<body>

  <!-- ==================== COVER PAGE ==================== -->
  <div class="cover-page">
    <div class="cover-decor"></div>
    <div class="cover-decor-bottom"></div>

    <div class="cover-header">
      <img src="${LOGO_GOLD}" alt="شعار تدلل الذهبي" class="cover-logo-img">
      <div class="cover-badge">إصدار المؤسسات | ENTERPRISE 2026</div>
    </div>

    <div class="cover-body">
      <span class="cover-title-label">دليل المستخدم الرسمي والمواصفات التشغيلية الشاملة</span>
      <h1 class="cover-main-title">منظومة <span>«تدلّل»</span> الذكية<br>لإدارة المطاعم وتجربة الضيوف</h1>
      <p class="cover-subtitle">
        المرجع التقني والتشغيلي المتكامل لملاك ومديري المطاعم، طواقم الخدمة، ورؤساء الطهاة.
        تغطية شاملة لكافة الخصائص: الطلب الذاتي عبر الـ <span dir="ltr">QR Code</span>، الويتر الذكي بالذكاء الاصطناعي، بوابة الواتساب الذاتية، عجلة الحظ، ونظام إدارة علاقات العملاء والولاء التراكمي.
      </p>

      <div class="cover-highlights-grid">
        <div class="cover-card">
          <span class="cover-card-icon">⚡</span>
          <div class="cover-card-title">بوابة واتساب مدمجة</div>
          <div class="cover-card-desc">ربط بمسح الـ <span dir="ltr">QR</span> في 10 ثوانٍ، بدون رسوم شهرية خارجية ومحرك ذكي لمكافحة الحظر.</div>
        </div>
        <div class="cover-card">
          <span class="cover-card-icon">🤖</span>
          <div class="cover-card-title">الويتر الذكي (Gemini)</div>
          <div class="cover-card-desc">مساعد ذكاء اصطناعي تفاعلي يقترح الأطباق ويرفع متوسط الفاتورة بمحادثة طبيعية.</div>
        </div>
        <div class="cover-card">
          <span class="cover-card-icon">🎯</span>
          <div class="cover-card-title">CRM وولاء تفاعلي</div>
          <div class="cover-card-desc">جمع بيانات الزبائن آلياً عبر عجلة الحظ، وتذكير بانتهاء الكوبونات قبل 24 ساعة.</div>
        </div>
      </div>
    </div>

    <div class="cover-footer">
      <div class="cover-meta-item">
        <strong>المنصة السحابية:</strong> <span dir="ltr">https://tedallal.tech</span><br>
        <strong>الإصدار:</strong> <span dir="ltr">v2.5.0 (Build 2026.11)</span>
      </div>
      <div class="cover-meta-item" style="text-align: left;" dir="ltr">
        <strong>Confidential & Proprietary</strong><br>
        Tedallal Restaurant Tech Inc. &copy; 2026
      </div>
    </div>
  </div>

  <!-- ==================== TABLE OF CONTENTS ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        منصة تدلّل للمطاعم الذكية
      </div>
      <div class="chapter-num-badge">فهرس المحتويات</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">📑</span>
      فهرس المحتويات وخريطة الدليل
    </h1>
    <p>
      تم تصميم هذا الدليل ليكون مرجعاً عملياً سريعاً وشاملاً لكافة أقسام المطعم: الإدارة العليا، مديري الفروع، الكاشير، الشيف وطاقم المطبخ، ومسؤولي التسويق.
    </p>

    <div class="toc-container">
      <ul class="toc-list">
        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">1. الرؤية الاستراتيجية والمعمارية التقنية</span>
            <span class="page-num">الباب الأول</span>
          </div>
          <ul class="toc-subitems">
            <li>1.1 فلسفة المنصة والقيمة المضافة</li>
            <li>1.2 البنية السحابية وهندسة العزل (<span dir="ltr">Multi-Tenant</span>)</li>
            <li>1.3 مصفوفة الصلاحيات والأدوار (<span dir="ltr">RBAC</span>)</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">2. تجربة الزبون والطلب الذاتي بالباركود</span>
            <span class="page-num">الباب الثاني</span>
          </div>
          <ul class="toc-subitems">
            <li>2.1 منظومة الباركود المشفر لكل طاولة</li>
            <li>2.2 تصفح المنيو الرقمي التفاعلي</li>
            <li>2.3 سلة الشراء وخيارات الدفع المرنة</li>
            <li>2.4 استدعاء الويتر وتتبع الطلب لحظياً</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">3. الويتر الذكي بالذكاء الاصطناعي</span>
            <span class="page-num">الباب الثالث</span>
          </div>
          <ul class="toc-subitems">
            <li>3.1 محرك المحادثة المدعوم بـ <span dir="ltr">Google Gemini</span></li>
            <li>3.2 التوصيات الذكية والبيع التكميلي (<span dir="ltr">Upselling</span>)</li>
            <li>3.3 إرشادات مسببات الحساسية والأنظمة الغذائية</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">4. عجلة الحظ والتسويق بالألعاب</span>
            <span class="page-num">الباب الرابع</span>
          </div>
          <ul class="toc-subitems">
            <li>4.1 تحفيز الضيوف وبناء قاعدة بيانات العملاء</li>
            <li>4.2 التحقق الإلزامي من رقم الواتساب الدولي</li>
            <li>4.3 نسب الاحتمالات وسقوف الخصومات</li>
            <li>4.4 تطبيق الكوبون التلقائي بنقرة واحدة في السلة</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">5. بوابة الواتساب الذاتية المدمجة</span>
            <span class="page-num">الباب الخامس</span>
          </div>
          <ul class="toc-subitems">
            <li>5.1 المعمارية المستقلة والخالية من الرسوم الخارجية</li>
            <li>5.2 خطوات الربط بمسح الكود في 10 ثوانٍ</li>
            <li>5.3 محرك مكافحة الحظر والمحاكاة البشرية (<span dir="ltr">Anti-Ban</span>)</li>
            <li>5.4 قوالب التنويع اللغوي الديناميكي (<span dir="ltr">Spintax</span>)</li>
            <li>5.5 الرد التلقائي الذكي على الرسائل الواردة</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">6. إدارة علاقات العملاء والولاء التراكمي</span>
            <span class="page-num">الباب السادس</span>
          </div>
          <ul class="toc-subitems">
            <li>6.1 سجل العملاء المعزول وبطاقة العميل الشاملة</li>
            <li>6.2 تصنيف الشرائح: <span dir="ltr">VIP</span>، منتظم، خامل</li>
            <li>6.3 جوائز المحطات الكبرى (<span dir="ltr">Milestone Rewards</span>)</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">7. محرك الأتمتة والتنبيهات الذكية</span>
            <span class="page-num">الباب السابع</span>
          </div>
          <ul class="toc-subitems">
            <li>7.1 الإرسال الخلفي التلقائي لكوبونات عجلة الحظ</li>
            <li>7.2 تذكيرات انتهاء الصلاحية قبل 24 ساعة (<span dir="ltr">FOMO</span>)</li>
            <li>7.3 رسائل استعادة الزبائن الخاملين بنقرة واحدة</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">8. شاشات المطبخ الذكية ونوافذ الشيف</span>
            <span class="page-num">الباب الثامن</span>
          </div>
          <ul class="toc-subitems">
            <li>8.1 نظام <span dir="ltr">KDS</span> الرقمي الخالي من الورق</li>
            <li>8.2 التلوين الزمني حسب مدة الانتظار والتنبيهات الصوتية</li>
            <li>8.3 نافذة الشيف المستقلة لتأكيد جاهزية كل طبق</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">9. إدارة الصالة، الطاولات، والموظفين</span>
            <span class="page-num">الباب التاسع</span>
          </div>
          <ul class="toc-subitems">
            <li>9.1 تخطيط الصالة الافتراضي وتعيين الأقسام</li>
            <li>9.2 طباعة باركودات الطاولات الفاخرة للطباعة</li>
            <li>9.3 إدارة حسابات الموظفين وتعيين الصلاحيات</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">10. هندسة المنيو والتحكم في التوفر</span>
            <span class="page-num">الباب العاشر</span>
          </div>
          <ul class="toc-subitems">
            <li>10.1 تنظيم التصنيفات والأصناف والخيارات</li>
            <li>10.2 زر التوفر الفوري (<span dir="ltr">In/Out of Stock Toggle</span>)</li>
            <li>10.3 تسعير الإضافات والضرائب ورسوم الخدمة</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">11. التقارير المالية والتحليلات المتقدمة</span>
            <span class="page-num">الباب الحادي عشر</span>
          </div>
          <ul class="toc-subitems">
            <li>11.1 لوحة المؤشرات التنفيذية (<span dir="ltr">Executive KPIs</span>)</li>
            <li>11.2 ساعات الذروة ومعدل دوران الطاولات</li>
            <li>11.3 مصفوفة ربحية الأطباق وتكلفة اكتساب العميل (<span dir="ltr">CAC</span>)</li>
          </ul>
        </li>

        <li class="toc-item">
          <div class="toc-item-title">
            <span class="title-text">12. الدليل التشغيلي وحل المشكلات</span>
            <span class="page-num">الباب الثاني عشر</span>
          </div>
          <ul class="toc-subitems">
            <li>12.1 روتين الفتح والإغلاق اليومي للمطعم</li>
            <li>12.2 مصفوفة استكشاف الأخطاء الشائعة وحلها السريع</li>
            <li>12.3 متطلبات التوافق التقني وباقات الاشتراك</li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="callout callout-gold avoid-break" style="margin-top: 24px;">
      <span class="callout-icon">💡</span>
      <div class="callout-content">
        <strong>توصية هامة لمديري المطاعم</strong>
        ننصح بطباعة الباب الثاني والباب الثامن وتوزيعهما على مشرفي الصالة ورؤساء الطهاة لتدريب الموظفين خلال 15 دقيقة فقط من بدء التشغيل.
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 1 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الأول | الرؤية والمعمارية التقنية
      </div>
      <div class="chapter-num-badge">صفحة 1</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">1</span>
      الرؤية الاستراتيجية والمعمارية التقنية
    </h1>

    <h2>1.1 نبذة عامة وفلسفة «تدلّل»</h2>
    <p>
      تُعد منصة <strong>«تدلّل» (Tedallal)</strong> حلاً برمجياً سحابياً متكاملاً (<span dir="ltr">All-in-One Cloud SaaS</span>) مصمماً خصيصاً لقطاع الضيافة والمطاعم الحديثة في الشرق الأوسط. تتمحور فلسفة النظام حول <strong>تحويل الطاولة من مجرد مساحة جلوس تقليدية إلى نقطة بيع ذكية وممتعة</strong> تُحفز العميل على استكشاف المنيو، وتزيد من معدل إنفاقه عبر تقنيات الذكاء الاصطناعي والتسويق بالألعاب، مع خفض العبء التشغيلي وتسريع دورة خدمة الطاولات.
    </p>

    <div class="mockup-container avoid-break">
      <img src="${SAAS_DASHBOARD}" alt="لوحة القيادة التنفيذية لمنصة تدلل">
      <div class="mockup-caption">الشكل (1.1): لوحة التحكم المركزية لمنصة «تدلّل» - نظرة تنفيذية شاملة على المبيعات، الطاولات، ونشاط المطبخ والواتساب</div>
    </div>

    <h2>1.2 البنية السحابية وهندسة العزل المتعدد (<span dir="ltr">Multi-Tenancy</span>)</h2>
    <p>
      بُنيت المنصة وفق أعلى معايير الحوسبة السحابية لضمان العزل التام بين بيانات المطاعم المختلفة مع أعلى درجات السرعة والأمان:
    </p>
    <ul>
      <li><strong>عزل قواعد البيانات والبيانات المشتركة:</strong> يتمتع كل مطعم برابط مخصص وسجل بيانات معزول برمجياً (<span dir="ltr">Tenant Scoping</span>) عبر <span dir="ltr">Global Scopes</span> تضمن استحالة وصول أي مطعم إلى بيانات عملاء أو مبيعات مطعم آخر.</li>
      <li><strong>عزل جلسات الواتساب:</strong> تعمل بوابة الواتساب كخدمة مصغرة مستقلة (<span dir="ltr">Microservice</span>) تُخصص مسار تخزين مشفر لكل مطعم (<span dir="ltr">storage/sessions/restaurant_{id}</span>)، مما يتيح تشغيل مئات المطاعم على خادم واحد دون أي تعارض في الاتصال أو اختلاط في الجلسات.</li>
      <li><strong>التزامن اللحظي للبيانات (<span dir="ltr">Real-time WebSockets & SSE</span>):</strong> تنقل المنصة كافة الطلبات وتغييرات الحالات بين جوال العميل، شاشة المطبخ، ولوحة الكاشير في أقل من 300 مللي ثانية.</li>
    </ul>

    <h2>1.3 مصفوفة الصلاحيات والأدوار (<span dir="ltr">RBAC</span>)</h2>
    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>الدور الوظيفي</th>
          <th>المسمى بالنظام</th>
          <th>نطاق الصلاحيات التشغيلية</th>
          <th>مستوى الحماية</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>مدير المطعم / المالك</strong></td>
          <td><span dir="ltr">Restaurant Admin</span></td>
          <td>التحكم الكامل: إعدادات المنيو، الأسعار، العروض، ربط الواتساب، إدارة الموظفين، والتقارير المالية والأرباح.</td>
          <td><span class="chip chip-purple">صلاحية عليا</span></td>
        </tr>
        <tr>
          <td><strong>مشرف الصالة / الكاشير</strong></td>
          <td><span dir="ltr">Cashier / Manager</span></td>
          <td>متابعة الطلبات، تعديل الفواتير، استلام المدفوعات النقدية، وإدارة حالات الطاولات الحية.</td>
          <td><span class="chip chip-blue">تشغيل مالي</span></td>
        </tr>
        <tr>
          <td><strong>رئيس الطهاة وطاقم المطبخ</strong></td>
          <td><span dir="ltr">Kitchen / Chef</span></td>
          <td>الوصول إلى شاشة المطبخ (<span dir="ltr">KDS</span>) ونافذة الشيف لتحديث حالات طهي وتسليم الأطباق فقط.</td>
          <td><span class="chip chip-green">تشغيل مطبخ</span></td>
        </tr>
        <tr>
          <td><strong>الويتر وطاقم الخدمة</strong></td>
          <td><span dir="ltr">Waiter / Service</span></td>
          <td>استلام إشعارات نداء الويتر وطلب الفاتورة، وتأكيد تقديم الأطباق الجاهزة للضيوف.</td>
          <td><span class="chip chip-gold">خدمة صالة</span></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ==================== CHAPTER 2 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الثاني | تجربة الزبون والطلب الذاتي
      </div>
      <div class="chapter-num-badge">صفحة 2</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">2</span>
      تجربة الزبون والطلب الذاتي بالباركود (<span dir="ltr">QR Code</span>)
    </h1>

    <h2>2.1 منظومة الباركود التفاعلي الديناميكي لكل طاولة</h2>
    <p>
      لا تتطلب منصة «تدلّل» من الضيف تحميل أي تطبيق إطلاقاً (<span dir="ltr">Zero App Download</span>). بمجرد فتح كاميرا الهاتف ومسح رمز الـ <span dir="ltr">QR Code</span> الموضوع على الطاولة، يتم فتح المنيو فوراً في متصفح الهاتف بسرعة فائقة مع التعرف التلقائي على رقم الطاولة وحالة الجلسة.
    </p>

    <div class="mockup-container avoid-break">
      <img src="${QR_PHONE}" alt="تجربة مسح الباركود واستعراض المنيو من هاتف العميل">
      <div class="mockup-caption">الشكل (2.1): تجربة الزبون السلسة - فتح المنيو التفاعلي فور مسح الباركود دون أي خطوات تسجيل معقدة</div>
    </div>

    <h2>2.2 استعراض المنيو التفاعلي وتخصيص الأطباق</h2>
    <div class="grid-2 avoid-break">
      <div class="feature-box highlight">
        <div class="feature-box-title">📷 صور سينمائية وأوصاف شهية</div>
        <div class="feature-box-desc">عرض عالي الدقة لكل طبق مع المكونات التفصيلية، القيمة الغذائية، وسعرات الأطباق لتعزيز الشهية واتخاذ قرار الشراء بسرعة.</div>
      </div>
      <div class="feature-box success">
        <div class="feature-box-title">🥗 تنبيهات الحساسية والخيارات الصحية</div>
        <div class="feature-box-desc">وسوم ذكية توضح الأطباق الخالية من الجلوتين، النباتية، الحارة، أو التي تحتوي على مكسرات ومشتقات الألبان لحماية صحة الضيوف.</div>
      </div>
      <div class="feature-box tech">
        <div class="feature-box-title">➕ الإضافات والتعديلات الحرة</div>
        <div class="feature-box-desc">إمكانية اختيار الحجم (صغير، وسط، كبير)، درجة النضج، إضافة جبن أو صوصات إضافية، وتدوين ملاحظات مخصصة للشيف (مثل: بدون بصل).</div>
      </div>
      <div class="feature-box">
        <div class="feature-box-title">🛒 سلة الطلبات العائمة الذكية</div>
        <div class="feature-box-desc">شريط عائم دائم يُظهر إجمالي الطلب، عدد الأصناف، ويسمح بتعديل الكميات وتطبيق كوبونات الخصم بلمسة واحدة.</div>
      </div>
    </div>

    <h2>2.3 خيارات الدفع ونداء طاقم الخدمة</h2>
    <div class="step-flow avoid-break">
      <div class="step-item">
        <div class="step-num">1</div>
        <div class="step-content">
          <h4>إرسال الطلب واعتماد الفاتورة</h4>
          <p>يضغط الضيف على "تأكيد الطلب"، فيصل فوراً إلى شاشات المطبخ وطابعات البونات، مع خيار الدفع كاش أو بالبطاقة أو الدفع الإلكتروني.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <div class="step-content">
          <h4>زر استدعاء الويتر بنقرة واحدة</h4>
          <p>في حال رغبة الضيف في خدمة سريعة أو ماء إضافي، يضغط على أيقونة "استدعاء الويتر"، فيظهر تنبيه صوتي فوري على جوال الويتر برقم الطاولة.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <div class="step-content">
          <h4>طلب الفاتورة الرقمية وإغلاق الحساب</h4>
          <p>عند الانتهاء، يطلب العميل الحساب مباشرة من جهازه ليحضر الكاشير جهاز نقاط البيع المحمول مباشرة دون انتظار أو تضييع للوقت.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 3 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الثالث | الويتر الذكي بالذكاء الاصطناعي
      </div>
      <div class="chapter-num-badge">صفحة 3</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">3</span>
      الويتر الذكي بالذكاء الاصطناعي (<span dir="ltr">AI Smart Waiter</span>)
    </h1>

    <h2>3.1 تقنية المساعد الشخصي المدعومة بـ <span dir="ltr">Google Gemini</span></h2>
    <p>
      تم تزويد منصة «تدلّل» بمساعد ذكاء اصطناعي تفاعلي متقدم مدمج مباشرة في واجهة المنيو. يعمل هذا المساعد بمثابة <strong>خبير ضيافة رقمي ومستشار طعام متمرس</strong> يتحدث باللغة العربية الطبيعية بطلاقة ويفهم مختلف اللهجات الدارجة في الخليج والشام ومصر.
    </p>

    <div class="callout callout-blue avoid-break">
      <span class="callout-icon">🤖</span>
      <div class="callout-content">
        <strong>كيف يتفاعل الويتر الذكي مع الضيوف؟</strong>
        يقوم المساعد بقراءة قائمة طعام المطعم ومكونات كل طبق بصورة حية، ويجيب على أسئلة العميل مثل: <em>"ما هو أفضل طبق دجاج لديكم؟"</em> أو <em>"هل شوربة الفطر تحتوي على كريمة حيوانية؟"</em> أو <em>"اقترح لي وجبة لشخصين ميزانيتها في حدود 150 ريال"</em>.
      </div>
    </div>

    <h2>3.2 محرك التوصيات والبيع التكميلي الذكي (<span dir="ltr">Smart Upselling & Cross-selling</span>)</h2>
    <p>
      على عكس المنيو الورقي الصامت، يدرس الويتر الذكي اختيارات العميل في السلة ويقترح إضافات مكملة ترفع متوسط قيمة الفاتورة (<span dir="ltr">Average Order Value - AOV</span>) بنسب تتراوح بين <strong>18% و 32%</strong>:
    </p>

    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>سلوك العميل في السلة</th>
          <th>اقتراح الويتر الذكي التفاعلي</th>
          <th>الأثر التجاري والمالي</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>إضافة وجبة ستيك مشوي فاخرة</td>
          <td>"ستيك رائع! هل تحب أن ترافقه بطبق مقبلات مشروم بري أو سلطة روكا طازجة؟"</td>
          <td>زيادة قيمة الفاتورة بإضافة مقبلات ذات هامش ربح مرتفع.</td>
        </tr>
        <tr>
          <td>إضافة وجبة برجر رئيسية بدون مشروب</td>
          <td>"لا يكتمل البرجر بدون مشروب موهيتو منعش أو بطاطس الوافل المقرمشة!"</td>
          <td>تحويل الصنف المفرد إلى وجبة كاملة بدون أي إلحاح من طاقم الصالة.</td>
        </tr>
        <tr>
          <td>عميل يتبع حمية الكيتو دايت</td>
          <td>"بناءً على طلبك، قمنا باختيار الأطباق الغنية بالبروتين والخالية تماماً من الكارب والسكريات."</td>
          <td>بناء ثقة فائقة مع العميل وتعزيز ولائه للمطعم.</td>
        </tr>
      </tbody>
    </table>

    <div class="callout callout-info avoid-break">
      <span class="callout-icon">🛡️</span>
      <div class="callout-content">
        <strong>حماية صارمة من مسببات الحساسية</strong>
        تمت برمجته ليتحقق من الحساسيات الشائعة (الفول السوداني، الجوز، البيض، الأسماك، الجلوتين). إذا استفسر الزبون عن حساسية معينة، يُنبهه النظام فورياً إذا كان الطبق يحتوي عليها ويقترح البديل الآمن مباشرة.
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 4 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الرابع | عجلة الحظ والتسويق بالألعاب
      </div>
      <div class="chapter-num-badge">صفحة 4</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">4</span>
      عجلة الحظ التفاعلية والتسويق بالألعاب (<span dir="ltr">Gamified Marketing</span>)
    </h1>

    <h2>4.1 استراتيجية التسويق بالألعاب وبناء أصول المطعم الرقمية</h2>
    <p>
      أثبتت الدراسات أن التسويق التفاعلي بالألعاب (<span dir="ltr">Gamification</span>) يزيد من تفاعل الضيوف في المطعم بنسبة تتجاوز <strong>40%</strong>. تقدم «تدلّل» لعبة <strong>عجلة الحظ (Lucky Wheel)</strong> كأداة عبقرية تدمج بين إمتاع الضيف وبناء أثمن أصل تسويقي للمطعم: <strong>قاعدة بيانات العملاء الحقيقية المؤكدة</strong>.
    </p>

    <h2>4.2 بوابات التحقق الإلزامي من أرقام الواتساب الدولية</h2>
    <p>
      للقضاء تماماً على محاولات التلاعب وإدخال أرقام وهمية، وضعت المنصة معايير تحقق صارمة قبل السماح بتدوير العجلة:
    </p>
    <ul>
      <li><strong>التحقق من صيغة الهاتف الدولية (<span dir="ltr">E.164 Validation</span>):</strong> التحقق من مفاتيح الدول (+966, +971, +965, إلخ) وطول الرقم الصحيح.</li>
      <li><strong>منع التكرار في نفس اليوم:</strong> تسجيل هاتف الزبون وجلسة الطاولة لمنع تدوير العجلة أكثر من مرة واحدة لكل جلسة.</li>
      <li><strong>التحقق من صحة الاسم الكامل:</strong> التأكد من إدخال اسم حقيقي لا يقل عن حرفين لتخصيص رسائل الواتساب اللاحقة.</li>
    </ul>

    <h2>4.3 مسار العميل التلقائي: التدوير، الفوز، وتفعيل الكوبون في السلة بلمسة واحدة</h2>
    <div class="step-flow avoid-break">
      <div class="step-item">
        <div class="step-num">1</div>
        <div class="step-content">
          <h4>تدوير العجلة والفوز الفوري</h4>
          <p>يقوم الضيف بتدوير العجلة، وتتوقف خوارزمية السحب على إحدى الجوائز المحددة مسبقاً (خصم 10%، 20%، طبق حلوى مجاني، إلخ).</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <div class="step-content">
          <h4>الإرسال الفوري للواتساب وحفظ الكود</h4>
          <p>يقوم النظام آلياً في الخلفية بتوليد كود خصم مشفر وإرساله مباشرة إلى واتساب الضيف مع إشعار أخضر في شاشة العجلة: "تم إرسال كود هديتك إلى واتسابك بنجاح!".</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <div class="step-content">
          <h4>رابط الانتقال السريع للمنيو مع تطبيق الخصم التلقائي</h4>
          <p>عند النقر على زر "اطلب الآن واستخدم هديتك" أو فتح الرابط في الواتساب (<span dir="ltr">/m/restaurant?coupon=WHEEL10</span>)، يُفتح المنيو ويُطبق الخصم في السلة تلقائياً دون الحاجة لكتابة أو نسخ الكود يدوياً!</p>
        </div>
      </div>
    </div>

    <div class="callout callout-gold avoid-break">
      <span class="callout-icon">⚙️</span>
      <div class="callout-content">
        <strong>إدارة نسب الاحتمالات وسقوف الجوائز من لوحة التحكم</strong>
        يستطيع مدير المطعم تحديد نسب الفوز بدقة متناهية (مثلاً: 50% لخصم 10%، 30% لخصم 15%، 15% لخصم 20%، و 5% لطبق مجاني)، مع إمكانية إيقاف أو تفعيل العجلة في أوقات الذروة بلمسة زر واحدة.
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 5 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الخامس | بوابة الواتساب الذاتية المدمجة
      </div>
      <div class="chapter-num-badge">صفحة 5</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">5</span>
      بوابة الواتساب الذاتية المدمجة (<span dir="ltr">WhatsApp Gateway</span>)
    </h1>

    <h2>5.1 المعمارية المستقلة: بدون رسوم خارجية (<span dir="ltr">Zero 3rd-Party Fees</span>)</h2>
    <p>
      واحدة من أعظم الميزات التنافسية في «تدلّل» هي تزويد كل مطعم بـ <strong>بوابة واتساب مدمجة كاملة تعمل ذاتياً على خادم المنصة</strong> عبر تقنية <span dir="ltr">Baileys WebSockets</span> المتطورة. هذا يعني:
    </p>
    <ul>
      <li><strong>انعدام التكاليف الإضافية:</strong> لا حاجة لشراء باقات رسائل من شركات وسيطة تكلف آلاف الريالات شهرياً، ولا حاجة لحسابات <span dir="ltr">Meta Business API</span> المعقدة وبطيئة الاعتماد.</li>
      <li><strong>استخدام رقم هاتف المطعم الحالي:</strong> يربط المطعم نفس رقمه المعروف لدى زبائنه مباشرة بالمنصة.</li>
      <li><strong>إرسال غير محدود للرسائل:</strong> إرسال رسائل الكوبونات، تذكيرات السلة، والردود التلقائية دون أي تكلفة رسالة على الإطلاق.</li>
    </ul>

    <h2>5.2 دليل الربط السريع في 10 ثوانٍ عبر مسح الـ <span dir="ltr">QR Code</span></h2>
    <div class="step-flow avoid-break">
      <div class="step-item">
        <div class="step-num">1</div>
        <div class="step-content">
          <h4>الدخول إلى قسم العملاء والواتساب</h4>
          <p>من لوحة تحكم المطعم، توجه إلى قائمة <strong>العملاء والـ CRM</strong>، واضغط على زر <strong>"ربط الواتساب"</strong>.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <div class="step-content">
          <h4>مسح الـ QR Code من تطبيق الواتساب</h4>
          <p>تفتح نافذة منبثقة تُظهر رمز استجابة سريعة يتحدث حياً كل 20 ثانية. افتح تطبيق واتساب على هاتف المطعم > الأجهزة المرتبطة > ربط جهاز > وجّه الكاميرا نحو الشاشة.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <div class="step-content">
          <h4>الاتصال الفوري وتفعيل الأتمتة</h4>
          <p>خلال ثانيتين فقط، يكتمل الاقتران وتتحول الشارة إلى اللون الأخضر الزاهي <strong>"متصل"</strong> مع إظهار رقم الهاتف وتاريخ الاتصال.</p>
        </div>
      </div>
    </div>

    <h2>5.3 محرك مكافحة الحظر والمحاكاة البشرية الذكية (<span dir="ltr">Anti-Ban Engine</span>)</h2>
    <p>
      لحماية أرقام المطاعم من الحظر من قِبل شركة واتساب، تم تجهيز البوابة بمحرك أمان فائق الذكاء:
    </p>
    <div class="grid-2 avoid-break">
      <div class="feature-box success">
        <div class="feature-box-title">✍️ محاكاة حالة الكتابة الحية (<span dir="ltr">Composing Presence</span>)</div>
        <div class="feature-box-desc">قبل إرسال أي رسالة، يُرسل النظام إشارة "جارٍ الكتابة..." إلى واتساب لمدة تتراوح بين 1.2 و 2.4 ثانية، مما يجعل سلوك البوابة مطابقاً تماماً لسلوك المستخدم البشري الطبيعي.</div>
      </div>
      <div class="feature-box tech">
        <div class="feature-box-title">🎲 محرك التنويع اللغوي الديناميكي (<span dir="ltr">Spintax Engine</span>)</div>
        <div class="feature-box-desc">يولد النظام تلقائياً صيغ تحية متنوعة عشوائياً (مرحباً، أهلاً وسهلاً بك، يسعد مساك، حياك الله) لتفادي البصمة الثابتة للرسائل الجماعية ومنع كشفها كرسائل سبام.</div>
      </div>
    </div>

    <h2>5.4 الرد التلقائي الذكي على رسائل الزبائن الواردة (<span dir="ltr">Inbound Auto-Reply</span>)</h2>
    <p>
      إذا قام أي زبون بمراسلة رقم المطعم على الواتساب للاستفسار، تقوم المنصة فوراً بالرد عليه برسالة ترحيبية مهذبة تتضمن رابط المنيو المباشر، مواعيد العمل، وموقع المطعم على خرائط جوجل.
    </p>
  </div>

  <!-- ==================== CHAPTER 6 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب السادس | إدارة علاقات العملاء والولاء
      </div>
      <div class="chapter-num-badge">صفحة 6</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">6</span>
      إدارة علاقات العملاء (<span dir="ltr">CRM</span>) وبرنامج الولاء التراكمي
    </h1>

    <h2>6.1 قاعدة بيانات الزبائن المعزولة والمؤتمتة</h2>
    <p>
      لا يحتاج المطعم إلى إدخال بيانات الزبائن يدوياً؛ حيث يتغذى نظام <strong>إدارة علاقات العملاء (<span dir="ltr">Customer CRM</span>)</strong> تلقائياً من مصدرين أساسيين:
    </p>
    <ol>
      <li><strong>مشاركات عجلة الحظ:</strong> التي تلتقط الاسم ورقم الواتساب الصحيح لكل ضيف في الصالة.</li>
      <li><strong>طلبات المنيو والفواتير:</strong> التي تسجل تفاصيل مشتريات كل عميل وأطباقه المفضلة وقيمة إنفاقه.</li>
    </ol>

    <h2>6.2 بطاقة العميل الرقمية الشاملة وتصنيف الشرائح</h2>
    <p>
      توفر المنصة بطاقة 360 درجة لكل عميل تشمل: الاسم، رقم الهاتف، إجمالي عدد الزيارات، إجمالي المبالغ المنفقة، تاريخ آخر زيارة، وسجل الكوبونات التي استخدمها أو التي لم يستغلها بعد.
    </p>

    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>شريحة العميل</th>
          <th>المعايير الخوارزمية</th>
          <th>الإجراء التسويقي التلقائي المقترح</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="chip chip-gold">عميل مميز | VIP</span></td>
          <td>أكثر من 5 زيارات أو إنفاق يفوق 1,000 ريال</td>
          <td>إرسال دعوات تجربة الأطباق الجديدة حصرياً، وهدايا أعياد الميلاد وجوائز استثنائية.</td>
        </tr>
        <tr>
          <td><span class="chip chip-green">عميل منتظم | Regular</span></td>
          <td>من زيارتين إلى 4 زيارات خلال آخر شهرين</td>
          <td>تحفيزه عبر برنامج مكافأة المحطة الكبرى لإكمال 10 زيارات.</td>
        </tr>
        <tr>
          <td><span class="chip chip-blue">عميل جديد | New</span></td>
          <td>زيارة واحدة فقط مسجلة حديثاً</td>
          <td>إرسال كود ترحيبي للزيارة الثانية خلال 7 أيام لترسيخ عادة الزيارة.</td>
        </tr>
        <tr>
          <td><span class="chip chip-red">عميل خامل / معرض للفقد | At Risk</span></td>
          <td>انقطاع عن المطعم لأكثر من 30 يوماً</td>
          <td>إرسال رسالة استعادة دافئة ("اشتقنا لك!") مع خصم 20% لتشجيعه على العودة فوراً.</td>
        </tr>
      </tbody>
    </table>

    <h2>6.3 برنامج مكافآت المحطات الكبرى (<span dir="ltr">Milestone Grand Rewards</span>)</h2>
    <p>
      يعتمد هذا البرنامج على مبدأ <strong>المكافأة التراكمية المشوقة</strong>، حيث يقوم مدير المطعم بتحديد هدف معين (مثلاً: 10 زيارات)، وعند وصول العميل للزيارة العاشرة، يُرسل له النظام تلقائياً عبر الواتساب جائزة كبرى (مثل: وجبة عشاء مجانية لشخصين أو خصم 50%).
    </p>

    <div class="callout callout-info avoid-break">
      <span class="callout-icon">📊</span>
      <div class="callout-content">
        <strong>العائد المالي المباشر لبرامج الولاء</strong>
        أثبتت بيانات المطاعم الشريكة في «تدلّل» أن برنامج المحطات الكبرى ساهم في رفع نسبة تكرار زيارة الزبائن بنسبة <strong>3.4 أضعاف</strong> مقارنة بالمطاعم التي لا تطبق برامج ولاء رقمية.
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 7 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب السابع | محرك الأتمتة والتنبيهات الذكية
      </div>
      <div class="chapter-num-badge">صفحة 7</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">7</span>
      محرك الأتمتة والتنبيهات الذكية (<span dir="ltr">Smart Automation</span>)
    </h1>

    <h2>7.1 دورة حياة الأتمتة التسويقية في «تدلّل»</h2>
    <p>
      تعمل أتمتة المنصة في الخلفية على مدار الساعة دون الحاجة لأي تدخل يدوي من موظفي المطعم، مما يوفر عشرات الساعات أسبوعياً ويضمن عدم ضياع أي فرصة بيعية.
    </p>

    <h2>7.2 حملة التذكير الذكي قبل انتهاء الصلاحية بـ 24 ساعة (<span dir="ltr">24h FOMO Reminder</span>)</h2>
    <p>
      غالباً ما ينسى الزبائن استخدام الكوبونات التي ربحوها من عجلة الحظ. لمعالجة ذلك، تم بناء مهمة مجدولة يومية تعمل في تمام الساعة الواحدة ظهراً (<span dir="ltr">Daily Cron at 13:00</span>):
    </p>

    <div class="step-flow avoid-break">
      <div class="step-item">
        <div class="step-num">1</div>
        <div class="step-content">
          <h4>فحص الكوبونات النشطة غير المستخدمة</h4>
          <p>يبحث النظام في قاعدة البيانات عن الكوبونات المكتسبة التي تنتهي صلاحيتها خلال الـ 24 ساعة القادمة ولم يقم العميل بتفعيلها بعد.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <div class="step-content">
          <h4>تطبيق محرك الخوف من فوات الفرصة (<span dir="ltr">FOMO Psychological Trigger</span>)</h4>
          <p>يتم إرسال رسالة واتساب ودية وشخصية: <em>"مرحباً [اسم الزبون]! نود تذكيرك بأن كود هديتك [CODE] بقيمة [VALUE] ينتهي غداً! لا تفوت طعم أطباقنا الشهية، اضغط هنا للطلب وتفعيل هديتك فوراً: [LINK]"</em>.</p>
        </div>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <div class="step-content">
          <h4>التحويل المباشر مع تطبيق الخصم بالسلة</h4>
          <p>يضغط العميل على الرابط فيتم فتح المنيو فوراً مع تطبيق الكوبون في السلة بنقرة واحدة، مما يحقق معدل تحويل واسترداد للكوبونات يفوق <strong>38%</strong>.</p>
        </div>
      </div>
    </div>

    <h2>7.3 أزرار التذكير اليدوي السريع بنقرة واحدة</h2>
    <p>
      في شاشة إدارة العملاء، يتوفر بجانب كل عميل خامل زر أخضر سريع <strong>"إرسال تذكير واتساب"</strong>، يسمح لمدير الفرع بإرسال رسالة موجهة فورية للزبون في ثوانٍ مع تتبع حالة التسليم لحظياً.
    </p>

    <div class="callout callout-gold avoid-break">
      <span class="callout-icon">✨</span>
      <div class="callout-content">
        <strong>أفضل ممارسات صياغة رسائل الواتساب التسويقية</strong>
        <ul>
          <li>اجعل الرسالة تبدأ باسم العميل دائماً لإعطاء شعور بالاهتمام الشخصي.</li>
          <li>وضح قيمة المكافأة المكتسبة بوضوح في السطر الأول.</li>
          <li>ضع دائماً رابط المنيو المباشر المشفر بالكوبون حتى لا يتكبد العميل عناء البحث أو النسخ.</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ==================== CHAPTER 8 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الثامن | شاشات المطبخ الذكية ونوافذ الشيف
      </div>
      <div class="chapter-num-badge">صفحة 8</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">8</span>
      شاشات المطبخ الذكية (<span dir="ltr">KDS</span>) ونوافذ الشيف
    </h1>

    <h2>8.1 المطبخ الرقمي الحديث الخالي من الورق (<span dir="ltr">Zero-Paper Kitchen</span>)</h2>
    <p>
      يستبدل نظام <strong>شاشة عرض المطبخ (<span dir="ltr">Kitchen Display System - KDS</span>)</strong> الفواتير الورقية المعرضة للضياع أو التلف بدهون وسوائل المطبخ بشاشات لوحية لمسية تعرض الطلبات بدقة لحظية متناهية وبألوان تنبيهية واضحة.
    </p>

    <div class="mockup-container avoid-break">
      <img src="${KDS_TABLET}" alt="شاشة المطبخ الذكية KDS في المطعم">
      <div class="mockup-caption">الشكل (8.1): شاشة المطبخ التفاعلية - تنظيم الطلبات حسب أولوية الطهي وتوقيت الوصول مع التمييز اللوني للأطباق</div>
    </div>

    <h2>8.2 مصفوفة التلوين الزمني والتنبيهات الصوتية الحية</h2>
    <div class="grid-3 avoid-break">
      <div class="feature-box success">
        <div class="feature-box-title">🟢 طلب جديد (أخضر)</div>
        <div class="feature-box-desc"><strong>أقل من 5 دقائق:</strong> وصل الطلب للتو من هاتف الزبون، مع رنين تنبيهي يجذب انتباه الطاهي للبدء بالتحضير.</div>
      </div>
      <div class="feature-box highlight">
        <div class="feature-box-title">🟠 قيد التحضير (برتقالي)</div>
        <div class="feature-box-desc"><strong>من 5 إلى 15 دقيقة:</strong> الطلب قيد الطهي في الوقت المسموح به لضمان الجودة والتسليم في الموعد المحدد.</div>
      </div>
      <div class="feature-box" style="border-top: 3px solid #ef4444;">
        <div class="feature-box-title">🔴 طلب متأخر (أحمر وامض)</div>
        <div class="feature-box-desc"><strong>أكثر من 15 دقيقة:</strong> تنبيه فوري يتطلب تدخلاً سريعاً من رئيس الطهاة لتفادي استياء الضيف في الصالة.</div>
      </div>
    </div>

    <h2>8.3 نافذة الشيف المستقلة لتأكيد جاهزية كل طبق (<span dir="ltr">Chef Item Window</span>)</h2>
    <p>
      تتميز «تدلّل» بإمكانية تحديث حالة كل طبق داخل نفس الطلب بصورة منفصلة:
    </p>
    <ul>
      <li><strong>تقديم المقبلات أولاً:</strong> يمكن للشيف وضع علامة "جاهز" على أطباق الشوربة والسلطة فور إعدادها، فيصل إشعار فوري للويتر لحملها للطاولة بينما يستمر طهي الوجبة الرئيسية.</li>
      <li><strong>وضوح الملاحظات الخاصة:</strong> تُعرض ملاحظات العميل (مثل: "بدون ملح"، "الصوص جانبي"، "حار جداً") بخط عريض ملون يستحيل إغفاله.</li>
    </ul>
  </div>

  <!-- ==================== CHAPTER 9 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب التاسع | إدارة الصالة، الطاولات، والموظفين
      </div>
      <div class="chapter-num-badge">صفحة 9</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">9</span>
      إدارة الصالة، الطاولات، وصلاحيات الموظفين
    </h1>

    <h2>9.1 التخطيط الرقمي للصالة وتوزيع الطاولات</h2>
    <p>
      تتيح المنصة تمثيل الصالة جغرافياً بما يطابق الواقع الفعلي للمطعم (صالة العائلات، قسم الأفراد، الشرفة الخارجية، الجلسات الخاصة، والغرف المغلقة).
    </p>

    <div class="mockup-container avoid-break">
      <img src="${FLOOR_PLAN}" alt="مخطط الصالة وتوزيع الطاولات وحالاتها الحية">
      <div class="mockup-caption">الشكل (9.1): مخطط الصالة التفاعلي - مراقبة إشغال الطاولات، أوقات الجلوس، وحالة كل طاولة لحظياً</div>
    </div>

    <h2>9.2 نظام توليد وطباعة بطاقات الـ <span dir="ltr">QR Code</span> الفاخرة للطاولات</h2>
    <p>
      بضغطة زر واحدة من لوحة التحكم، يستطيع مدير المطعم توليد وطباعة ملفات <span dir="ltr">PDF</span> عالية الدقة جاهزة للمطابع لكل طاولة:
    </p>
    <ul>
      <li><strong>تصميم راقٍ ومتوافق مع الهوية:</strong> شعار المطعم مدمج في وسط الباركود مع إطار أنيق يوجه الضيف لمسح الكود.</li>
      <li><strong>روابط أمان مشفرة:</strong> تمنع التلاعب بأرقام الطاولات وتمنع مسح الباركود من خارج المطعم عبر توثيق النطاق والجلسة.</li>
      <li><strong>تصدير فردي أو جماعي:</strong> إمكانية طباعة طاولة واحدة بعد استبدال حامل الأكريليك، أو طباعة جميع طاولات الفرع دفعة واحدة.</li>
    </ul>

    <h2>9.3 حالات الطاولات التشغيلية الحية</h2>
    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>حالة الطاولة</th>
          <th>الرمز اللوني</th>
          <th>المعنى التشغيلي لطاقم الخدمة</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>شاغرة | Vacant</strong></td>
          <td><span class="chip chip-green">أخضر</span></td>
          <td>الطاولة نظيفة تماماً ومعقمة وجاهزة لاستقبال الضيوف الجدد فوراً.</td>
        </tr>
        <tr>
          <td><strong>مشغولة | Occupied</strong></td>
          <td><span class="chip chip-blue">أزرق</span></td>
          <td>الضيوف متواجدون حالياً، والطلب قيد التحضير أو التناول.</td>
        </tr>
        <tr>
          <td><strong>قيد التنظيف | Cleaning</strong></td>
          <td><span class="chip chip-gold">برتقالي</span></td>
          <td>غادر الضيوف بعد دفع الحساب، والمطلوب من طاقم الصالة تعقيم الطاولة فوراً.</td>
        </tr>
        <tr>
          <td><strong>محجوزة | Reserved</strong></td>
          <td><span class="chip chip-purple">بنفسجي</span></td>
          <td>الطاولة مخصصة لعميل قام بالحجز المسبق ولن تتاح للدخول العشوائي.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ==================== CHAPTER 10 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب العاشر | هندسة المنيو والتحكم في التوفر
      </div>
      <div class="chapter-num-badge">صفحة 10</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">10</span>
      هندسة المنيو، المخزون، والتسعير الذكي
    </h1>

    <h2>10.1 هندسة القائمة وتصنيف الأطباق (<span dir="ltr">Menu Engineering</span>)</h2>
    <p>
      تم بناء إدارة المنيو في «تدلّل» استناداً إلى أحدث نظريات هندسة القوائم لتعظيم الربحية وسهولة تصفح الضيوف:
    </p>
    <ul>
      <li><strong>ترتيب التصنيفات المرئي:</strong> سحب وإفلات التصنيفات لتقديم الأطباق الأكثر ربحية والمقبلات أولاً.</li>
      <li><strong>الشارات الترويجية البارزة:</strong> وضع وسوم مثل <em>"الأكثر مبيعاً ⭐"</em> أو <em>"توقيع الشيف 👨‍🍳"</em> أو <em>"صحي 🌿"</em> لجذب أنظار الزبائن نحو الأطباق المستهدفة.</li>
      <li><strong>الأحجام المتعددة والإضافات:</strong> إضافة خيارات الأحجام (عائلي، مفرد)، وتحديد إذا كانت الإضافة اختيارية أو إلزامية وبأسعار ديناميكية.</li>
    </ul>

    <h2>10.2 زر التوفر اللحظي (<span dir="ltr">In-Stock / Out-of-Stock Switch</span>)</h2>
    <p>
      أكبر مشكلة تواجه المطاعم في المنيو الورقي هي إحراج الزبون عندما يطلب طبقاً نفدت مكوناته من المطبخ. في «تدلّل»، يتم حل هذه المشكلة في <strong>ثانية واحدة</strong>:
    </p>

    <div class="callout callout-alert avoid-break">
      <span class="callout-icon">⚡</span>
      <div class="callout-content">
        <strong>التحكم الفوري بنفاد المكونات</strong>
        بمجرد نفاد أي مادة أولية من المطبخ (مثل اللحم أو السمك)، يفتح الشيف أو مدير الصالة لوحة المنيو ويغلق مفتاح التوفر بجانب الطبق. في نفس اللحظة بالضبط، يُعرض الطبق كـ "غير متوفر" على هواتف كافة الزبائن في الصالة دون أي حاجة لإعادة طباعة ورقة واحدة أو إحراج طاقم الخدمة!
      </div>
    </div>

    <h2>10.3 الضرائب، رسوم الخدمة، والعملات المتعددة</h2>
    <p>
      يدعم النظام ضبط ضريبة القيمة المضافة (<span dir="ltr">VAT 15%</span> أو حسب متطلبات الدولة) لتُحسب تلقائياً مع خيار تضمين الضريبة في سعر الطبق أو إضافتها في ملخص الفاتورة النهائي، مع إمكانية إضافة رسوم الخدمة أو التوصيل حسب الحاجة.
    </p>
  </div>

  <!-- ==================== CHAPTER 11 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الحادي عشر | التقارير المالية والتحليلات
      </div>
      <div class="chapter-num-badge">صفحة 11</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">11</span>
      التقارير المالية والتحليلات والذكاء التجاري
    </h1>

    <h2>11.1 لوحة المؤشرات التنفيذية الرئيسية (<span dir="ltr">Executive KPIs</span>)</h2>
    <p>
      تمنح «تدلّل» مالك المطعم ومديره رؤية استراتيجية واضحة ودقيقة لاتخاذ قرارات تسعيرية وتشغيلية صائبة استناداً إلى بيانات حية لا تقبل الشك.
    </p>

    <div class="mockup-container avoid-break">
      <img src="${ANALYTICS_CHART}" alt="لوحة الرسوم البيانية والتحليلات المالية في تدلل">
      <div class="mockup-caption">الشكل (11.1): الرسوم البيانية التفاعلية - تتبع نمو الإيرادات، ساعات الذروة، ونسب أرباح الأطباق الأكثر مبيعاً</div>
    </div>

    <h2>11.2 مصفوفة تصنيف الأطباق وتحليل الربحية</h2>
    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>تصنيف الطبق</th>
          <th>حجم المبيعات</th>
          <th>هامش الربح</th>
          <th>القرار التشغيلي الموصى به</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>الأطباق النجمية (Stars ⭐)</strong></td>
          <td>مرتفع جداً</td>
          <td>مرتفع جداً</td>
          <td>المحافظة على أعلى معايير الجودة وتثبيتها في صدارة المنيو والتسويق لها بقوة.</td>
        </tr>
        <tr>
          <td><strong>أبقار النقد (Cash Cows 🐄)</strong></td>
          <td>مرتفع جداً</td>
          <td>منخفض إلى متوسط</td>
          <td>إعادة دراسة تكلفة المكونات ومحاولة تخفيضها أو رفع السعر بهدوء لزيادة الربح.</td>
        </tr>
        <tr>
          <td><strong>الأطباق اللغز (Puzzles 🧩)</strong></td>
          <td>منخفض</td>
          <td>مرتفع جداً</td>
          <td>أطباق مربحة جداً لكن إقبال الزبائن عليها ضعيف؛ يجب تكليف الويتر الذكي باقتراحها.</td>
        </tr>
        <tr>
          <td><strong>الأطباق العبء (Dogs 🐕)</strong></td>
          <td>منخفض</td>
          <td>منخفض</td>
          <td>أطباق تستهلك مساحة ومكونات بلا عائد؛ يُفضل إزالتها واستبدالها فوراً.</td>
        </tr>
      </tbody>
    </table>

    <h2>11.3 تحليل تكلفة اكتساب العميل (<span dir="ltr">CAC</span>) والعائد الاستثماري (<span dir="ltr">ROI</span>)</h2>
    <p>
      يحسب النظام آلياً تكلفة الخصومات الممنوحة عبر عجلة الحظ وبرنامج الولاء مقارنة بإجمالي الإيرادات التي أنفقها هؤلاء العملاء في زياراتهم اللاحقة، مما يثبت للمالك العائد الاستثماري الضخم لكل ريال تم منحه كخصم تشجيعي.
    </p>
  </div>

  <!-- ==================== CHAPTER 12 ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الباب الثاني عشر | الدليل التشغيلي وحل المشكلات
      </div>
      <div class="chapter-num-badge">صفحة 12</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">12</span>
      الدليل التشغيلي اليومي ومصفوفة حل المشكلات
    </h1>

    <h2>12.1 جدول الفتح والإغلاق اليومي للمطعم (<span dir="ltr">Opening & Closing Routine</span>)</h2>
    <div class="grid-2 avoid-break">
      <div class="feature-box success">
        <div class="feature-box-title">☀️ إجراءات بداية اليوم (صباحاً)</div>
        <div class="feature-box-desc">
          1. تشغيل شاشات المطبخ (<span dir="ltr">KDS</span>) والتأكد من اتصال شبكة الإنترنت.<br>
          2. التأكد من حالة بوابة الواتساب في اللوحة (الشارة الخضراء "متصل").<br>
          3. مراجعة توفر الأصناف وإلغاء تفعيل أي صنف لم تكتمل شحنته الصباحية.<br>
          4. تفقد حوامل الـ <span dir="ltr">QR Code</span> على الطاولات ونظافتها.
        </div>
      </div>
      <div class="feature-box highlight">
        <div class="feature-box-title">🌙 إجراءات نهاية اليوم (مساءً)</div>
        <div class="feature-box-desc">
          1. التأكد من إغلاق وحساب كافة الطلبات المفتوحة في شاشة الكاشير.<br>
          2. تصدير ملخص مبيعات اليوم ومطابقة مبالغ الكاش مع تقرير المنصة.<br>
          3. إعادة تعيين حالات الطاولات إلى "شاغرة" استعداداً لليوم التالي.<br>
          4. ترك الأجهزة على قواعد الشحن الكهربائية.
        </div>
      </div>
    </div>

    <h2>12.2 مصفوفة استكشاف الأخطاء وحلها السريع (<span dir="ltr">Troubleshooting Guide</span>)</h2>
    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>المشكلة الشائعة</th>
          <th>السبب المحتمل</th>
          <th>الحل الفوري الموصى به</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>حالة الواتساب تظهر "غير متصل"</strong></td>
          <td>انقطاع الإنترنت عن جوال المطعم أو تسجيل خروج من واتساب.</td>
          <td>تأكد من اتصال الجوال بالإنترنت، ثم اضغط "إعادة ربط" وامسح الـ <span dir="ltr">QR Code</span> الجديد خلال 10 ثوانٍ.</td>
        </tr>
        <tr>
          <td><strong>الطلب لا يظهر في شاشة المطبخ</strong></td>
          <td>انقطاع الاتصال بشبكة الصالة في جهاز الشاشة اللوحي.</td>
          <td>تحديث الصفحة بالضغط على زر التحديث، أو التأكد من اتصال الواي فاي.</td>
        </tr>
        <tr>
          <td><strong>الزبون لم يستلم كود الخصم على واتسابه</strong></td>
          <td>إدخال رقم الهاتف بصيغة غير صحيحة أو بدون مفتاح الدولة.</td>
          <td>يمكن للكاشير أو المدير فتح ملف الزبون في شاشة <span dir="ltr">CRM</span> وإعادة إرسال الكود بنقرة زر واحدة.</td>
        </tr>
        <tr>
          <td><strong>الكاميرا لا تقرأ باركود الطاولة</strong></td>
          <td>اتساخ أو تلف حامل الباركود، أو ضعف الإضاءة المحيطة.</td>
          <td>تنظيف الحامل، أو إعادة طباعة باركود جديد للطاولة من قسم إدارة الطاولات.</td>
        </tr>
      </tbody>
    </table>

    <div class="callout callout-info avoid-break" style="margin-top: 24px;">
      <span class="callout-icon">📞</span>
      <div class="callout-content">
        <strong>فريق الدعم الفني وخدمة العملاء على مدار الساعة</strong>
        فريق الدعم التقني لـ «تدلّل» متواجد على مدار الساعة لمساعدتكم في أي استفسار أو تدريب إضافي عبر الرقم الموحد أو من خلال المحادثة الفورية في لوحة التحكم.
      </div>
    </div>
  </div>

  <!-- ==================== APPENDIX: PLANS & SPECS ==================== -->
  <div class="page-break">
    <div class="chapter-header">
      <div class="chapter-brand-pill">
        <img src="${LOGO_GOLD}" alt="تدلل">
        الملحق | باقات الاشتراك والمواصفات الفنية
      </div>
      <div class="chapter-num-badge">ملحق المواصفات</div>
    </div>

    <h1 class="chapter-title">
      <span class="num-circle">⭐</span>
      باقات الاشتراك والمواصفات الفنية المعتمدة
    </h1>

    <h2>مقارنة باقات الاشتراك السحابية لمنصة «تدلّل»</h2>
    <table class="guide-table avoid-break">
      <thead>
        <tr>
          <th>الميزة والمواصفة</th>
          <th>الباقة الأساسية (<span dir="ltr">Basic</span>)</th>
          <th>الباقة المتقدمة (<span dir="ltr">Pro</span>)</th>
          <th>الباقة الشاملة (<span dir="ltr">Max Enterprise</span>)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>المنيو الرقمي والطلب بالباركود</strong></td>
          <td>✅ متاح (حتى 15 طاولة)</td>
          <td>✅ متاح (طاولات غير محدودة)</td>
          <td>✅ متاح (طاولات وفروع متعددة)</td>
        </tr>
        <tr>
          <td><strong>الويتر الذكي بالذكاء الاصطناعي</strong></td>
          <td>❌ غير متوفر</td>
          <td>✅ متاح (Google Gemini)</td>
          <td>✅ متاح + تخصيص أسلوب المحادثة</td>
        </tr>
        <tr>
          <td><strong>عجلة الحظ التفاعلية والتسويق</strong></td>
          <td>❌ غير متوفر</td>
          <td>✅ متاح (سحب شهري محدود)</td>
          <td>✅ متاح غير محدود + تحكم كامل بالنسب</td>
        </tr>
        <tr>
          <td><strong>بوابة الواتساب الذاتية المدمجة</strong></td>
          <td>❌ غير متوفر</td>
          <td>✅ متوفرة مع مانع الحظر</td>
          <td>✅ متوفرة + أتمتة تذكيرات 24h كاملة</td>
        </tr>
        <tr>
          <td><strong>نظام إدارة العملاء (CRM) والولاء</strong></td>
          <td>سجل طلبات مبسط</td>
          <td>✅ CRM كامل وتصنيف شرائح</td>
          <td>✅ برنامج المحطات الكبرى التراكمي</td>
        </tr>
        <tr>
          <td><strong>شاشات المطبخ الذكية (KDS)</strong></td>
          <td>شاشة واحدة</td>
          <td>حتى 3 شاشات</td>
          <td>شاشات غير محدودة + نوافذ الشيف</td>
        </tr>
        <tr>
          <td><strong>التقارير المالية وهندسة المنيو</strong></td>
          <td>تقارير يومية أساسية</td>
          <td>✅ متقدمة مع رسوم بيانية</td>
          <td>✅ ذكاء تجاري كامل + تحليل CAC & ROI</td>
        </tr>
        <tr>
          <td><strong>مستوى الدعم الفني</strong></td>
          <td>بريد إلكتروني (خلال 24 س)</td>
          <td>واتساب وأولوية دعم</td>
          <td>مدير حساب مخصص + دعم فوري 24/7</td>
        </tr>
      </tbody>
    </table>

    <div class="mockup-container avoid-break" style="margin-top: 30px;">
      <div style="background: #090d16; padding: 24px 30px; display: flex; justify-content: space-between; align-items: center; color: white;">
        <div>
          <h3 style="color: #f59e0b; margin: 0 0 6px 0; font-size: 16px;">ابدأ التحول الرقمي لمطعمك اليوم مع «تدلّل»</h3>
          <p style="color: #cbd5e1; margin: 0; font-size: 12px;">انضم إلى مئات المطاعم الرائدة التي ضاعفت أرباحها وولاء ضيوفها بأحدث تقنيات الضيافة الذكية.</p>
        </div>
        <img src="${TECH_QR}" alt="باركود الدخول السريع لتدلل" style="height: 75px; background: white; padding: 4px; border-radius: 8px;">
      </div>
      <div class="mockup-caption">امسح الكود لزيارة موقع المنصة الرسمي: <span dir="ltr">https://tedallal.tech</span></div>
    </div>
  </div>

</body>
</html>`;

const tempHtmlPath = path.join(process.env.TEMP, 'tedallal_user_guide_source.html');
const outputPdfPath = path.join(__dirname, 'Tedallal_Complete_User_Guide.pdf');
const publicPdfPath = path.join(__dirname, 'public', 'Tedallal_Complete_User_Guide.pdf');
const brainPdfPath = 'C:\\\\Users\\\\ASUS\\\\.gemini\\\\antigravity\\\\brain\\\\d252c7a9-d1be-4051-9046-25eeac8a68b9\\\\Tedallal_Complete_User_Guide.pdf';

fs.writeFileSync(tempHtmlPath, html, 'utf8');
console.log(`>>> [3/5] Temporary HTML written to ${tempHtmlPath} (${(fs.statSync(tempHtmlPath).size / 1024).toFixed(1)} KB)`);

console.log('>>> [4/5] Invoking Headless Chromium/Edge to render ultra-high-definition PDF...');
const edgePath = 'C:\\\\Program Files (x86)\\\\Microsoft\\\\Edge\\\\Application\\\\msedge.exe';

const renderResult = spawnSync(edgePath, [
  '--headless=new',
  '--disable-gpu',
  '--run-all-compositor-stages-before-draw',
  '--no-pdf-header-footer',
  `--print-to-pdf=${outputPdfPath}`,
  tempHtmlPath
], { stdio: 'inherit' });

if (fs.existsSync(outputPdfPath)) {
  const pdfSize = fs.statSync(outputPdfPath).size;
  console.log(`>>> [5/5] SUCCESS! PDF Generated successfully: ${outputPdfPath}`);
  console.log(`>>> File Size: ${(pdfSize / (1024 * 1024)).toFixed(2)} MB (${pdfSize} bytes)`);

  // Copy to public folder
  fs.copyFileSync(outputPdfPath, publicPdfPath);
  console.log(`>>> Copied to Public Web Directory: ${publicPdfPath}`);

  // Copy to Brain Artifacts directory
  try {
    fs.copyFileSync(outputPdfPath, brainPdfPath);
    console.log(`>>> Copied to Brain Artifacts Directory: ${brainPdfPath}`);
  } catch (err) {
    console.warn('Could not copy to brain artifacts directory directly:', err.message);
  }

  // Cleanup temp HTML
  try {
    fs.unlinkSync(tempHtmlPath);
  } catch (_) {}
} else {
  console.error('ERROR: PDF was not created by Chromium/Edge!');
  process.exit(1);
}
