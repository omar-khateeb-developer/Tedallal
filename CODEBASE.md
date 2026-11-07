# Tedallal — Codebase Reference Map

> **الغرض:** مرجع سريع للذكاء الاصطناعي يُغني عن قراءة الملفات في كل طلب.
> **المشروع:** منصة إدارة مطاعم B2B — Laravel 11 + Vue 3 + Inertia.js + Tailwind CSS
> **المستودع:** `g:\tedallal كامل مع disable\tedallal`
> **آخر تحديث:** 2026-11-07

---

## 1. Stack التقني

| الطبقة | التقنية |
|--------|---------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue 3 (Composition API + `<script setup>`), TypeScript |
| Bridge | Inertia.js v2 |
| Styling | Tailwind CSS v4 |
| State (Admin) | Axios (REST) + `useForm()` Inertia |
| Auth | Laravel Sanctum + Spatie Permissions |
| DB | MySQL / SQLite (dev) |
| Build | Vite + vue-tsc |
| Queue | Database driver |

---

## 2. بنية قاعدة البيانات (أعمدة كل جدول)

### `users`
```
id, name, email, email_verified_at, password, remember_token,
restaurant_id (FK→restaurants nullable), default_window (tinyint nullable),
timestamps
```
**Roles (Spatie):** `super_admin`, `admin`, `cashier`, `chef`, `waiter`, `staff`, `supervisor`

---

### `restaurants`
```
id, name, slug (unique), type ('postpaid'|'prepaid'), plan ('basic'|'pro'|'max'),
windows_count (tinyint default:1), logo, phone, address,
currency (char3 default:'SYP'), status (enum→RestaurantStatus),
navbar_image, features (JSON), subscription_expires_at,
timestamps
```
**Helper Methods:** `isPrepaid()`, `hasOffersFeature()`, `plan_label`, `effective_features`, `is_expired`, `days_remaining`

---

### `restaurant_tables`
```
id, restaurant_id (FK), name, public_token (64 unique), status ('active'|...), timestamps
```

---

### `table_sessions`
```
id, table_id (FK→restaurant_tables cascade), session_token (64 unique),
status ('active'|'closed'), started_at (NOT NULL), closed_at (nullable), timestamps
```
⚠️ `started_at` هو NOT NULL — يجب تمريره عند الإنشاء.

---

### `menu_categories`
```
id, restaurant_id (FK), name, description, image (nullable),
sort_order (uint default:0), is_active (bool default:true), timestamps
```

---

### `menu_items`
```
id, restaurant_id (FK), category_id (FK→menu_categories),
name, description, price (decimal), image (nullable),
is_active (bool), sort_order (uint default:0), timestamps
```

---

### `cart_items`
```
id, session_id (FK→table_sessions), menu_item_id (FK),
quantity (uint), unit_price (decimal), note (text nullable), timestamps
```

---

### `orders`
```
id, restaurant_id (FK), table_session_id (FK), order_number (string unique),
status ('pending'|'accepted'|'preparing'|'ready'|'served'|'cancelled'),
total (decimal), promo_code_id (FK→promo_codes nullable),
discount_amount (decimal default:0), customer_note (text nullable),
service_type ('dine_in'|'takeaway'), pickup_window (tinyint nullable),
paid_at (timestamp nullable), paid_by (FK→users nullable),
accepted_at, preparing_at, ready_at, served_at, cancelled_at (timestamps nullable),
timestamps
```

---

### `order_items`
```
id, order_id (FK cascade), menu_item_id (FK), name, quantity, unit_price,
subtotal, note (nullable), timestamps
```

---

### `promo_codes`
```
id, restaurant_id (FK cascade),
code (string, index; UNIQUE per restaurant),
discount_type (enum: 'percentage' | 'fixed'),
discount_value (decimal 10,2),
min_order_value (decimal 10,2 default:0),
is_active (bool default:true),
starts_at (timestamp nullable),
is_single_use (bool default:false),
times_used (uint default:0),
expires_at (timestamp nullable),
timestamps
```

**منطق الكوبونات:**
- `is_single_use = false` → كوبون رئيسي (Master) = يظهر في العجلة كـ "جائزة"
- `is_single_use = true` → قسيمة مولودة (Voucher) = يتم إنشاؤها بعد الفوز في العجلة بكود `LUCK-XXXXXX`
- الكوبون صالح إذا: `is_active=true AND starts_at <= now AND expires_at > now AND times_used < 1 (إذا single_use)`

---

### `order_wheel_tokens`
```
id, restaurant_id (FK), order_id (FK unique cascade),
token (64 unique index),
is_claimed (bool default:false), claimed_at (nullable),
won_promo_code_id (FK→promo_codes nullable nullOnDelete),
generated_promo_code_id (FK→promo_codes nullable nullOnDelete),
expires_at (timestamp nullable),
timestamps
```

---

### `activity_logs`
```
id, user_id (FK nullable), restaurant_id (FK nullable),
action (string), description (text nullable),
ip_address (string nullable), timestamps
```

---

### `consultation_leads`
```
id, name, restaurant_name, phone, city (nullable),
service_type (nullable), payment_model (nullable), kitchen_setup (nullable),
desired_features (JSON nullable), notes (text nullable),
ip_address (string nullable), user_agent (text nullable),
status (default:'new'), timestamps
```

---

### `settings`
```
id, key (unique), value (text nullable), timestamps
```

---

## 3. Models وعلاقاتهم

```
Restaurant
  hasMany → User, RestaurantTable, MenuCategory, MenuItem, Order, PromoCode, OrderWheelToken
  appends → logo, navbar_image, plan_label, effective_features, is_expired, days_remaining

User
  belongsTo → Restaurant
  HasRoles (Spatie): super_admin | admin | cashier | chef | waiter | staff | supervisor
  Methods: isSuperAdmin(), isAdmin(), isCashier(), isChef(), isSupervisor(), isWaiter(), isStaff()

RestaurantTable
  belongsTo → Restaurant
  hasMany → TableSession

TableSession
  belongsTo → RestaurantTable
  hasMany → CartItem, Order
  ⚠️ started_at REQUIRED at creation

Order
  belongsTo → Restaurant, TableSession, PromoCode (nullable)
  hasMany → OrderItem
  hasOne → OrderWheelToken

PromoCode
  belongsTo → Restaurant
  HasTenant trait (global scope: restaurant_id = auth user's restaurant)
  Methods: isValid(?float), calculateDiscount(float), getValidationDiagnostics(...)

OrderWheelToken
  belongsTo → Restaurant, Order
  belongsTo → wonPromoCode (PromoCode), generatedPromoCode (PromoCode)
  Methods: isExpired()

MenuItem
  belongsTo → Restaurant, MenuCategory

MenuCategory
  belongsTo → Restaurant
  hasMany → MenuItem
```

---

## 4. Controllers Map

### Web Controllers (Inertia Pages)
| Controller | Route Prefix | الوصف |
|-----------|-------------|-------|
| `Web/CustomerController` | `/t/{token}` | قائمة العميل، السلة، تتبع الطلب |
| `Web/WheelController` | `/wheel/{token}` | عجلة الحظ (show, spin, reset) |
| `Web/ConsultationController` | `/consultation` | صفحة استمارة ما قبل البيع |
| `Web/StaffController` | `/staff/orders` | شاشة طلبات الكاشير/الشيف |
| `Web/SuperAdminController` | `/super-admin/` | لوحة السوبر أدمن |
| `Web/Admin/DashboardController` | `/admin/` | لوحة الأدمن |
| `Web/Admin/OrderPrintController` | `/admin/orders/{id}/print` | طباعة الطلب |
| `Web/Admin/TablePrintController` | `/admin/tables/print` | طباعة QR الطاولات |

### API Controllers (JSON — Auth: Sanctum)
| مجموعة | مسار | الوصف |
|--------|------|-------|
| `Api/Public/` | `/api/public/` | بدون auth — العميل، السلة، الجلسة |
| `Api/Admin/` | `/api/admin/` + `auth:sanctum + role:admin` | إدارة المطعم |
| `Api/SuperAdmin/` | `/api/super-admin/` | إدارة عامة |

**API Admin المهمة:**
- `GET/POST/PATCH/DELETE /api/admin/promo-codes` → `AdminPromoCodeController` (apiResource)
  - `?type=master` → `is_single_use=false` (كوبونات العجلة وكوبونات الخصم)
  - `?type=wheel` → `is_single_use=true` (القسائم المولودة بعد الفوز)
  - `?type=all` → الكل
- `GET /api/admin/orders` → قائمة الطلبات
- `PATCH /api/admin/orders/{id}/status` → تحديث الحالة
- `GET /api/admin/analytics` → التحليلات
- `GET /api/admin/reports/sales` → تقارير المبيعات

---

## 5. Vue Pages Map

### `/resources/js/Pages/`
```
Welcome.vue           ← الصفحة الرئيسية (Landing) — RTL، Bilingual AR/EN، Dark Luxury Theme
Wheel.vue             ← عجلة الحظ — Canvas Retina، Web Audio، Voucher Ticket
Maintenance.vue       ← صفحة الصيانة
Dashboard.vue         ← redirect handler

Admin/
  Dashboard.vue       ← لوحة الأدمن الرئيسية (stats, orders KDS)
  Offers.vue          ← إدارة كوبونات الخصم (Master + Wheel tabs)
  Orders.vue          ← قائمة الطلبات
  Tables.vue          ← إدارة الطاولات وQR
  MenuCategories.vue  ← إدارة أقسام المنيو
  MenuItems.vue       ← إدارة عناصر المنيو
  Users.vue           ← إدارة الموظفين
  Analytics.vue       ← التحليلات والمخططات
  Reports.vue         ← تقارير المبيعات
  Settings.vue        ← إعدادات المطعم

Customer/
  Menu.vue            ← منيو العميل (QR Scan)
  Cart.vue            ← سلة التسوق
  OrderStatus.vue     ← تتبع الطلب الحي + بنر عجلة الحظ

Auth/                 ← Login, Register, Reset, etc.
Consultation/Index.vue ← استمارة ما قبل البيع
Staff/Orders.vue      ← شاشة KDS للكاشير والشيف
SuperAdmin/           ← Dashboard, Users, Leads, CreateRestaurant
Profile/              ← Edit Profile + sub-partials
```

---

## 6. Lucky Wheel — Architecture (عجلة الحظ)

### التدفق الكامل:
```
1. العميل يطلب → يُنشأ Order → يُنشأ OrderWheelToken (token فريد، expires 24h)
2. CustomerController يمرر wheelToken للعميل في OrderStatus.vue
3. العميل يفتح /wheel/{token}
4. WheelController::show() → يبني slices من promo_codes (is_single_use=false, is_active=true)
5. العميل يضغط "دوّر" → POST /wheel/{token}/spin
6. WheelController::spin() → يختار فائز عشوائياً → ينشئ PromoCode جديد (is_single_use=true, code=LUCK-XXXXXX)
7. يُحفظ won_promo_code_id و generated_promo_code_id في OrderWheelToken
8. تُعرض للعميل تذكرة القسيمة الفاخرة
```

### بناء Slices في WheelController::buildSlicesForRestaurant():
- يجلب PromoCode: `is_active=true AND is_single_use=false`
- يمدد القائمة لـ 8 شرائح (تكرار إذا أقل)
- **label يُبنى من `discount_type` و `discount_value` — وليس من `code`**
  - `percentage` → `"خصم %{value}"`
  - `fixed` → `"خصم {value} {currency}"`

### Demo Mode:
- `GET /wheel/demo` أو `GET /wheel-demo` → يعمل بدون token حقيقي
- يستخدم `DEFAULT_REWARDS` ثابتة (8 جوائز)
- Spin demo → يعيد `DEMO-XXXXXX` بدون تسجيل في DB

### CSRF Exemptions (bootstrap/app.php):
```
wheel/*/spin
wheel/*/reset
```

---

## 7. Roles & Permissions

| Role | الصلاحيات |
|------|-----------|
| `super_admin` | كل شيء + إدارة المطاعم والمستخدمين |
| `admin` | إدارة مطعمه: قائمة، طاولات، موظفين، تقارير، كوبونات |
| `cashier` | `manage_orders` → شاشة الطلبات فقط |
| `chef` | `manage_orders` → شاشة KDS فقط |
| `supervisor` | `manage_orders` + `manage_tables` |
| `waiter` | `manage_orders` |
| `staff` | `manage_orders` |

---

## 8. Design System (Frontend)

### التوجه العام:
- **Dark Luxury Glassmorphism** — خلفية `#0a0a0a` إلى `#121212`
- **RTL** بالكامل (`dir="rtl"`)
- **ثنائي اللغة:** AR (افتراضي) + EN — عبر `currentLocale` ref + `translations` object

### Typography:
- Headers: `font-serif` (Amiri) — ذهبي `bg-gradient-to-r from-[#BF953F] via-[#FCF6BA] to-[#B38728] bg-clip-text text-transparent`
- Body: `font-tajawal` (Tajawal)

### Cards القياسية:
```css
relative bg-black/40 backdrop-blur-xl border border-white/5
shadow-[inset_0_1px_1px_rgba(255,255,255,0.05),0_20px_40px_rgba(0,0,0,0.5)]
before:absolute before:-inset-[1px] before:bg-gradient-to-b
before:from-[#D4AF37]/40 before:to-transparent before:-z-10
before:rounded-[inherit] before:blur-sm
transition-all duration-500
hover:before:from-[#D4AF37]/80 hover:shadow-[0_0_35px_rgba(212,175,55,0.2)]
```

### Gold Gradient:
```css
bg-gradient-to-r from-[#BF953F] via-[#FCF6BA] to-[#B38728]
```

### Primary Button (Gold):
```css
bg-gradient-to-r from-[#BF953F] via-[#FCF6BA] to-[#B38728] text-black
shadow-[0_12px_40px_rgba(212,175,55,0.35)]
hover:shadow-[0_18px_50px_rgba(212,175,55,0.5)]
transition-all duration-500 hover:-translate-y-1
```

---

## 9. ملفات مهمة أخرى

| الملف | الوصف |
|-------|-------|
| `resources/js/locales/landing.ts` | ترجمات صفحة Welcome (AR + EN) |
| `resources/js/composables/useTheme.ts` | Dark/Light mode toggle |
| `resources/js/composables/useRealtimeOrders.ts` | polling الطلبات الحية |
| `app/Traits/HasTenant.php` | Global Scope يفلتر بـ restaurant_id تلقائياً |
| `app/Services/QrCodeService.php` | توليد QR codes للطاولات |
| `bootstrap/app.php` | CSRF Exemptions + Middleware |
| `config/app.php` | إعدادات التطبيق |
| `public/images/` | الصور الثابتة (شعارات، خلفيات، QR) |

---

## 10. الصور المتاحة في `public/images/`

```
tedallal-luxury-hero-bg.jpg        ← خلفية Hero Section (Michelin-star interior)
tedallal-luxury-saas-mockup.jpg    ← Mockup شاشة المنصة
tedallal-flagship-facade.jpg       ← واجهة المقر الرئيسي
tedallal-golden-crop.png           ← الشعار الذهبي (محاصيل)
tedallal-golden-logo.png           ← الشعار الكامل
tedallal-golden-logo-tight.png     ← الشعار (محاذاة ضيقة)
tedallal-golden-logo-pure.png      ← الشعار (نقي بدون خلفية)
tedallal-tech-qr.png               ← QR كود موقع tedallal.tech
tedallal-og-banner.jpg             ← بانر Open Graph (1200x630)
luxury-restaurant-hero-bg.jpg      ← خلفية سينمائية (بدون نصوص)
```

---

## 11. Git History (آخر 5 commits)

```
bf3cf8d  feat(loyalty): professional lucky wheel overhaul — retina physics, audio, demo mode
adbf0c9  feat(ui): Bespoke Carved Digital Luxury — glowing gold pseudo-elements + SVG grain
8530f34  feat(ui): Handcrafted Dark Luxury Glassmorphism hero + asymmetrical features
4801282  feat(ui): Bespoke Dark Luxury redesign — Bento Box grid, branded QR logos
ddaebcf  feat(launch-assets): sales rep playbook PDF + high-res golden QR
```

---

## 12. قواعد التطوير في هذا المشروع

1. **لا تُنشئ scratch files في الجذر** — اكتبها في `.gemini/antigravity/brain/.../scratch/`
2. **بعد كل تعديل Vue.js** → شغّل `npm run build` وتحقق من exit code 0
3. **بعد كل تعديل** → `git add -A && git commit -m "..."` ثم `git push origin main`
4. **الخادم يعمل دائماً على** `php artisan serve --port=8000` (task-4518)
5. **العملة الافتراضية:** `ل.س` (SYP) — يُعرض كـ `ل.س` في الواجهة
6. **CSRF مُعفى من:** `wheel/*/spin` و `wheel/*/reset`
7. **لا تعبّر عن القيمة الوهمية** في عجلة الحظ — استخدم دائماً `discount_type` و `discount_value` من DB
