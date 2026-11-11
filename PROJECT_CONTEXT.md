# Tedallal Platform (تدلّل) — Project Context & Memory
> **وثيقة الذاكرة والسياق السريع للذكاء الاصطناعي (AI Context & Architecture Index)**  
> **الغرض:** قراءة هذا الملف في بداية أي مهمة يُغني عن فحص واستكشاف عشرات الملفات من الصفر، ويوفر استهلاك التوكنز بشكل جذري.  
> **آخر تحديث:** 2026-11-11

---

## 1. الستاك التقني (Tech Stack)
* **Backend:** Laravel 11.x (PHP 8.4).
* **Frontend:** Inertia.js v2 + Vue 3 (Composition API / `<script setup lang="ts">`) + Tailwind CSS v4.
* **Realtime:** Laravel Reverb + Laravel Echo WebSockets (لشاشات الكاشير، المطبخ، وحالات الطلبات).
* **Database:** MySQL في الإنتاج، SQLite/MySQL محلياً.
* **Assets:** Vite Bundler (`npm run build`).

---

## 2. الضوابط والقواعد الصارمة (Strict Invariants)
1. **قيد النشر والرفع الصارم:** ممنوع نهائياً تنفيذ `git push` أو أي أمر نشر إلى السيرفر (Deploy) دون موافقة صريحة ومباشرة من المستخدم.
2. **محاذاة العربية والترتيب (RTL):** عزل الكلمات والمصطلحات والروابط الأجنبية لمنع تداخل الأحرف وتلف سياق القراءة من اليمين لليسار.
3. **تعدد المستأجرين (Multi-Tenancy):**
   * عزل بيانات كل مطعم تلقائياً عبر `restaurant_id`.
   * **استثناء حرج:** المسارات العامة للزبائن (`/t/{public_token}` أو فحص كوبونات الطاولة) تتطلب تجاوز النطاق العام باستخدام `withoutGlobalScopes()` لأن العميل غير مسجل الدخول لا يحمل جلسة `restaurant_id` تلقائية.

---

## 3. محددات عجلة الحظ والكوبونات (Lucky Wheel & Promo Invariants)
* **الكوبونات الحقيقية للمطعم (`LUCK-XXXXXX`):**
  * تبدأ صلاحيتها بعد ساعتين لزيارة العميل القادمة (`starts_at = now()->addHours(2)`).
  * صالحة لمرة واحدة فقط (`is_single_use = true`).
* **الكوبونات التجريبية (`DEMO-XXXXXX`):**
  * مفعلة فوراً للتجربة بدون انتظار (`starts_at = now()->subHours(2)`).
  * معفاة صراحة من فحص `isFuture` في دالتي `isValid()` و `getValidationDiagnostics()` في نموذج `PromoCode.php`.
  * يتم التعرف عليها وتسجيلها تلقائياً بالخصم المكتسب عند إدخالها في السلة في `PromoCodeController.php`.
* **العرض على القرص:** يُلغى أي نص وهمي، وتُعرض القيمة الحقيقية للخصم (`discount_value`) ونوعه مباشرة من إعدادات المطعم.

---

## 4. نظام الطباعة وشاشة الكاشير (Printing & POS Rules)
* **أزرار الطباعة:** يُحجب زر الطباعة للطلبات الجارية حتى تصبح جاهزة للتسليم لمنع تكرار الطباعة العشوائي.
* **الطباعة التلقائية (`auto_print_on_ready`):** خيار مخصص في إعدادات كل مطعم يحدد توقيت الطباعة الأوتوماتيكية للفاتورة (عند اكتمال تحضير الطلب لتوضع مع الوجبة، أو عند التسليم النهائي).

---

## 5. معايير واجهات الموبايل (Mobile UX Standards)
* **منع التكبير القسري (No Auto-Zoom):** جميع حقول الإدخال (`<input>`, `<select>`, `<textarea>`) يجب ألا يقل حجم خطها عن `16px` على شاشات الموبايل لتفادي قيام متصفحات Safari/Chrome بعمل Zoom-in يحجب الـ Modal.
* **إعدادات العرض:** الـ Viewport مثبت بـ `width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover`.

---

## 6. بيئة السيرفر والاستقرار (Production Environment & Stability)
* **خادم الإنتاج:** Ubuntu VPS (`194.163.44.154`) على المسار `/var/www/tedallal` — الدومين: `https://tedallal.tech`.
* **حد الذاكرة (Memory Limit):** مثبت عند `512M` في `php8.4-fpm` (لمنع خطأ 500 أثناء معالجة الصور واستعلامات السلة).
* **الأمان:** وضع التصحيح مغلق دائماً في الإنتاج (`APP_DEBUG=false`).
* **سلامة السلة:** عدم إطلاق استثناء مباشر عند حذف صنف، بل تنظيف السلة بلباقة وتنبيه الزبون دون انهيار الصفحة.

---

## 7. الخريطة المباشرة لأهم الملفات (Core Files Map)
| المسار | الوظيفة الأساسية |
| :--- | :--- |
| `app/Models/PromoCode.php` | منطق فحص صلاحية الكوبونات واستثناءات التجريب (`isDemoCode`) |
| `app/Http/Controllers/Api/Public/PromoCodeController.php` | تطبيق الكوبونات في السلة والتحقق منها |
| `app/Http/Controllers/Web/WheelController.php` | منطق عجلة الحظ، توليد الجوائز، والوضع التجريبي (`/wheel/demo`) |
| `resources/js/Pages/Wheel.vue` | واجهة عجلة الحظ الفيزيائية، بطاقة الهدية، ومشاركة الواتساب |
| `resources/js/Pages/Menu.vue` | قائمة طعام الزبون، السلة، ونافذة تأكيد الطلب |
| `resources/js/Pages/Admin/Orders.vue` | شاشة الكاشير، إرسال واتساب، واعتماد الدفع والطباعة |
| `resources/js/Pages/Admin/Offers.vue` | إدارة جوائز العجلة وعروض المطعم |
| `resources/js/Pages/Admin/Settings.vue` | إعدادات المطعم (نوع التشغيل، الطباعة التلقائية، العملة) |
| `app/Services/CartService.php` | حساب مجاميع السلة، الضرائب، والخصومات |
| `routes/web.php` & `routes/api.php` | توجيه مسارات المنصة والـ API |

