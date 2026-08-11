# تدلّل (Tedallal) — Architecture Document

> Smart Table Service Platform · MVP · Troy Agency  
> Version: 1.0 · Status: Approved for implementation

---

## Phase 1 — Environment Analysis

| Item | Current State |
|------|---------------|
| Framework | Laravel **13.8** |
| PHP | **^8.3** |
| Frontend | **Vue 3 + TypeScript + Vite + Tailwind CSS** |
| Auth scaffold | **Laravel Breeze (Inertia)** |
| API auth package | **Laravel Sanctum** (installed, not configured) |
| Database (dev default) | SQLite (will switch default to **PostgreSQL**) |
| Git | Not initialized |
| Custom domain code | **None** — default Breeze only |
| Tests | Breeze auth/profile tests only |

---

## 1. Architecture Overview

### Frontend Choice: **Laravel + Inertia.js + Vue 3 + TypeScript (Hybrid with REST API)**

**Why Inertia (not a fully separated SPA)?**

- The project is already scaffolded with Breeze + Inertia — zero throwaway work.
- Admin dashboard CRUD maps naturally to Inertia (forms, validation errors, auth session).
- Single deploy unit: one Laravel app serves admin, customer UI, and API.
- Shared auth/session for staff without token juggling in the admin UI.

**Why also expose REST API?**

- The spec defines explicit `/api/public/*` and `/api/admin/*` endpoints.
- Customer cart/order interactions benefit from JSON API (no full page reloads).
- Keeps the door open for future mobile apps without rewriting business logic.
- Admin order polling/real-time can hit the same API layer.

**Pattern:**

```
┌─────────────────────────────────────────────────────────────┐
│                     Laravel Application                      │
├─────────────────────────────────────────────────────────────┤
│  Inertia (Web)          │  REST API (JSON)                   │
│  • Admin dashboard      │  • /api/public/*  (session token)  │
│  • Staff orders UI      │  • /api/admin/*   (Sanctum)        │
│  • Customer shell /t/*  │                                    │
├─────────────────────────────────────────────────────────────┤
│  Controllers (thin) → Form Requests → Services → Models     │
├─────────────────────────────────────────────────────────────┤
│  PostgreSQL │ Queue │ Filesystem │ Events/Listeners         │
└─────────────────────────────────────────────────────────────┘
```

Business logic lives in **Services** and **Domain enums/value objects**. Controllers and Inertia controllers delegate to the same services as API controllers.

---

## 2. Database ERD

```mermaid
erDiagram
    RESTAURANTS ||--o{ RESTAURANT_TABLES : has
    RESTAURANTS ||--o{ MENU_CATEGORIES : has
    RESTAURANTS ||--o{ MENU_ITEMS : has
    RESTAURANTS ||--o{ ORDERS : receives
    RESTAURANTS ||--o{ USERS : employs

    RESTAURANT_TABLES ||--o{ TABLE_SESSIONS : hosts
    TABLE_SESSIONS ||--o{ ORDERS : contains
    TABLE_SESSIONS ||--o{ CART_ITEMS : holds

    MENU_CATEGORIES ||--o{ MENU_ITEMS : contains
    MENU_ITEMS ||--o{ ORDER_ITEMS : referenced_by
    MENU_ITEMS ||--o{ CART_ITEMS : referenced_by

    ORDERS ||--o{ ORDER_ITEMS : contains

    USERS {
        bigint id PK
        bigint restaurant_id FK
        string name
        string email UK
        string password
        enum role "admin|staff"
        enum status "active|inactive"
        timestamps
    }

    RESTAURANTS {
        bigint id PK
        string name
        string slug UK
        string logo nullable
        string phone nullable
        text address nullable
        char currency "3-letter ISO"
        enum status "active|inactive"
        timestamps
    }

    RESTAURANT_TABLES {
        bigint id PK
        bigint restaurant_id FK
        string name
        string public_token UK
        enum status "active|inactive"
        timestamps
    }

    TABLE_SESSIONS {
        bigint id PK
        bigint table_id FK
        string session_token UK
        enum status "active|closed"
        timestamp started_at
        timestamp closed_at nullable
        timestamps
    }

    MENU_CATEGORIES {
        bigint id PK
        bigint restaurant_id FK
        string name
        text description nullable
        string image nullable
        int sort_order
        boolean is_active
        timestamps
    }

    MENU_ITEMS {
        bigint id PK
        bigint restaurant_id FK
        bigint category_id FK
        string name
        text description nullable
        string image nullable
        decimal price "12,2"
        int sort_order
        boolean is_available
        boolean is_active
        timestamps
    }

    CART_ITEMS {
        bigint id PK
        bigint table_session_id FK
        bigint menu_item_id FK
        int quantity
        text note nullable
        timestamps
    }

    ORDERS {
        bigint id PK
        bigint restaurant_id FK
        bigint table_id FK
        bigint table_session_id FK
        string order_number UK
        enum status
        decimal subtotal
        decimal total
        text customer_note nullable
        timestamps
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint menu_item_id FK "nullable, set null on delete"
        string item_name_snapshot
        decimal unit_price
        int quantity
        decimal subtotal
        text note nullable
        timestamps
    }
```

> **Note:** Table model is named `RestaurantTable` (class) mapped to `restaurant_tables` table to avoid SQL reserved word `tables`.

---

## 3. Entity Relationships

| Parent | Child | Relationship | On Delete |
|--------|-------|--------------|-----------|
| Restaurant | User | 1:N | restrict |
| Restaurant | RestaurantTable | 1:N | restrict |
| Restaurant | MenuCategory | 1:N | cascade |
| Restaurant | MenuItem | 1:N | cascade |
| Restaurant | Order | 1:N | restrict |
| RestaurantTable | TableSession | 1:N | cascade |
| RestaurantTable | Order | 1:N | restrict |
| TableSession | Order | 1:N | restrict |
| TableSession | CartItem | 1:N | cascade |
| MenuCategory | MenuItem | 1:N | cascade |
| MenuItem | OrderItem | 1:N | set null |
| MenuItem | CartItem | 1:N | cascade |
| Order | OrderItem | 1:N | cascade |

**Indexes (additional):**

- `restaurant_tables(public_token)` UNIQUE
- `table_sessions(session_token)` UNIQUE
- `table_sessions(table_id, status)` — find active session per table
- `orders(restaurant_id, status, created_at)`
- `orders(order_number)` UNIQUE
- `menu_items(restaurant_id, category_id, is_active, is_available)`
- `cart_items(table_session_id, menu_item_id)` UNIQUE — one row per item per session

---

## 4. Tables & Columns (Full)

See ERD above. Additional design notes:

### Money
- PostgreSQL `NUMERIC(12,2)` for all monetary fields.
- PHP: cast to `decimal:2` string; calculations via `Brick\Math` or BCMath in `MoneyCalculator` service — never `float`.

### Future customization hook
- `order_items.note` and `cart_items.note` support per-item notes now.
- `menu_items` table stays flat; future `menu_item_options` / `order_item_modifiers` tables can attach without breaking existing orders.

### Order number
- Format: `ORD-{YYYYMMDD}-{4-digit daily sequence}` per restaurant, generated inside transaction with row lock.

---

## 5. API Routes

### Public (no auth; rate-limited; session via `X-Session-Token` header or httpOnly cookie)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/public/tables/{token}` | Resolve table + restaurant |
| POST | `/api/public/sessions` | Create/resume active session for table |
| GET | `/api/public/sessions/{sessionToken}` | Session details + cart summary |
| GET | `/api/public/menu` | Active menu (categories + items) for restaurant |
| GET | `/api/public/menu/categories` | Categories only |
| POST | `/api/public/cart/items` | Add/update cart item |
| PATCH | `/api/public/cart/items/{cartItem}` | Update qty/note |
| DELETE | `/api/public/cart/items/{cartItem}` | Remove cart item |
| DELETE | `/api/public/cart` | Clear cart |
| POST | `/api/public/orders` | Submit order from cart |
| GET | `/api/public/orders/{orderNumber}` | Order status (scoped to session token) |

### Admin (Sanctum + role middleware)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/admin/dashboard/stats` | Dashboard metrics |
| GET | `/api/admin/orders` | List orders (filterable) |
| GET | `/api/admin/orders/{order}` | Order detail |
| PATCH | `/api/admin/orders/{order}/status` | Transition status |
| GET | `/api/admin/tables` | List tables |
| POST | `/api/admin/tables` | Create table |
| PATCH | `/api/admin/tables/{table}` | Update table |
| DELETE | `/api/admin/tables/{table}` | Delete if safe |
| POST | `/api/admin/tables/{table}/regenerate-token` | New public_token + QR |
| GET | `/api/admin/tables/{table}/qr` | QR image/svg |
| GET | `/api/admin/tables/qr/print-all` | Printable all QRs |
| CRUD | `/api/admin/menu/categories` | Category management |
| CRUD | `/api/admin/menu/items` | Item management |
| GET/PATCH | `/api/admin/restaurant` | Restaurant settings |
| CRUD | `/api/admin/users` | User management (admin only) |

### Web (Inertia)

| Route | Page |
|-------|------|
| `/t/{publicToken}` | Customer menu (Arabic RTL) |
| `/t/{publicToken}/cart` | Cart review |
| `/t/{publicToken}/orders/{orderNumber}` | Order tracking |
| `/admin/*` | Admin dashboard pages |
| `/staff/orders` | Staff order board |

Staff login uses existing Breeze `/login`. Admin routes use `role:admin` middleware; staff routes use `role:admin,staff`.

---

## 6. Authentication Strategy

| Actor | Method |
|-------|--------|
| Admin / Staff | Email + password via Breeze session auth |
| Admin API | Laravel Sanctum SPA cookie auth (same session) OR token for future |
| Customer | **No account** — anonymous table session |

**Customer session security:**

1. QR resolves `public_token` → creates `TableSession` with cryptographically random `session_token` (32 bytes, base64url).
2. Token stored in **httpOnly, Secure, SameSite=Lax** cookie `tedallal_session`.
3. All cart/order API calls validate cookie token matches session and session is `active` and belongs to resolved table.
4. Public order lookup requires both `order_number` AND valid session token (prevents enumeration).

**Sanctum:** Enable SPA authentication for admin Inertia pages calling `/api/admin/*`.

---

## 7. Authorization Strategy

### Roles (enum on `users.role`)

| Role | Capabilities |
|------|-------------|
| `admin` | Full access: settings, users, menu, tables, QR, orders |
| `staff` | View/update orders only; no menu/table/user/settings management |

### Implementation

- `UserRole` enum: `Admin`, `Staff`
- Gates: `Gate::define('manage-restaurant', ...)`, `Gate::define('manage-orders', ...)`
- Policies: `OrderPolicy`, `RestaurantTablePolicy`, `MenuCategoryPolicy`, `MenuItemPolicy`, `UserPolicy`, `RestaurantPolicy`
- Middleware: `EnsureUserHasRole:admin`, `EnsureUserHasRole:admin,staff`
- **No** inline `$user->role === 'admin'` in controllers

### Staff restrictions (MVP)

Staff **cannot**: create/edit/delete tables, menu, users, restaurant settings, regenerate QR tokens.

---

## 8. QR Strategy

| Concern | Decision |
|---------|----------|
| Token generation | `Str::random(32)` → base64url encoded (~43 chars), loop until unique |
| Public URL | `{APP_URL}/t/{public_token}` |
| QR content | Full public URL (not raw token alone) |
| Library | `endroid/qr-code` (maintained, PHP 8.3 compatible) |
| Service | `App\Services\QrCodeService` |
| Storage | `storage/app/public/qr/{restaurant_id}/{public_token}.png` |
| Regeneration | New token invalidates old QR URLs immediately |
| Print layout | Blade view `/admin/tables/print-qr` — restaurant name, table name, QR, "امسح للطلب" |

---

## 9. Table Session Strategy

```
Customer scans QR
    → GET /t/{public_token}
    → Backend: find active RestaurantTable by public_token
    → If inactive table → 404
    → TableSessionService::resolveOrCreate(table)
         • If active session exists for table → resume (same session_token)
         • Else → create new session, close any stale sessions
    → Set session cookie
    → Render menu Inertia page
```

**Session lifecycle:**

- **active**: customer can cart/order
- **closed**: manually by staff/admin OR auto after configurable idle timeout (future; MVP: manual close from admin)
- One **active** session per table at a time (enforced by partial unique index or service lock)

**Multiple orders per session:** Supported. Each `POST /api/public/orders` creates a new Order from current cart, then clears cart.

---

## 10. Order State Machine

```
                    ┌─────────────┐
                    │   pending   │
                    └──────┬──────┘
                           │ accept
                    ┌──────▼──────┐
         cancel ◄───┤  accepted   ├──► cancel (staff/admin, before preparing)
                    └──────┬──────┘
                           │ start_preparing
                    ┌──────▼──────┐
         cancel ◄───┤  preparing  │ (cancel only admin, MVP: disallow cancel once preparing)
                    └──────┬──────┘
                           │ mark_ready
                    ┌──────▼──────┐
                    │    ready    │
                    └──────┬──────┘
                           │ mark_served
                    ┌──────▼──────┐
                    │   served    │ (terminal)
                    └─────────────┘

cancel from pending/accepted → cancelled (terminal)
```

**Service:** `OrderStatusTransitionService`

- Validates allowed transitions via `OrderStatus` enum `canTransitionTo()`
- Logs transition in application log
- Dispatches `OrderStatusChanged` event

**Cancellation rules (MVP):**

| From | Who | Allowed |
|------|-----|---------|
| pending | staff/admin | yes |
| accepted | staff/admin | yes |
| preparing | admin only | yes (edge case: out of stock) |
| ready, served | — | no |
| any | customer | no (MVP) |

---

## 11. Frontend Page Map

### Customer (Arabic-first, RTL, mobile-first)

| Page | Route | Key features |
|------|-------|--------------|
| Table Menu | `/t/{token}` | Restaurant branding, table name, category tabs, item cards |
| Item detail modal | overlay | Description, price, add to cart, note |
| Cart | `/t/{token}/cart` | Line items, qty controls, subtotal, submit |
| Order confirmation | `/t/{token}/orders/{num}` | Order number, status badge, polling |
| Invalid table | `/t/invalid` | Friendly Arabic error |

**Layout:** `CustomerLayout.vue` — bottom cart bar, RTL dir, IBM Plex Sans Arabic / Cairo font.

### Admin (authenticated)

| Page | Route |
|------|-------|
| Dashboard | `/admin/dashboard` |
| Orders list | `/admin/orders` |
| Order detail | `/admin/orders/{id}` |
| Tables | `/admin/tables` |
| QR codes | `/admin/tables` (inline actions) |
| Print all QR | `/admin/tables/print` |
| Menu categories | `/admin/menu/categories` |
| Menu items | `/admin/menu/items` |
| Restaurant settings | `/admin/settings` |
| Users | `/admin/users` |

### Staff

| Page | Route |
|------|-------|
| Order board | `/staff/orders` | Kanban-style or list with quick status buttons |

**i18n:** Laravel lang files `lang/ar/*.php` + Vue composable `useI18n()` reading shared Inertia props. Default locale `ar`, fallback `en`.

---

## 12. Testing Strategy

### Unit Tests (`tests/Unit/`)

| Test class | Covers |
|------------|--------|
| `MoneyCalculatorTest` | Addition, multiplication, no float drift |
| `OrderTotalCalculatorTest` | Subtotal/total from items |
| `OrderStatusTransitionTest` | Valid/invalid transitions |
| `PublicTokenGeneratorTest` | Uniqueness, length, charset |
| `OrderNumberGeneratorTest` | Format, daily sequence |

### Feature Tests (`tests/Feature/`)

| Test | Spec # |
|------|--------|
| `TableQrResolutionTest` — valid token | Test 1 |
| `TableQrResolutionTest` — invalid token → 404 | Test 2 |
| `OrderCreationTest` — unavailable item rejected | Test 3 |
| `OrderPriceSnapshotTest` — price change doesn't affect history | Test 4 |
| `AdminAuthorizationTest` — staff blocked from admin ops → 403 | Test 5 |
| `OrderTransactionTest` — failure leaves no partial order | Test 6 |
| `SessionIsolationTest` — cannot access other table's session | Security |
| `CartTest` — CRUD operations scoped to session |
| `MenuVisibilityTest` — inactive/unavailable hidden |

### Test DB
- PostgreSQL in CI; SQLite acceptable for local fast runs with `phpunit.xml` env override.

---

## 13. Project Structure (Target)

```
app/
├── Enums/
│   ├── OrderStatus.php
│   ├── TableStatus.php
│   ├── SessionStatus.php
│   └── UserRole.php
├── Events/
│   ├── OrderCreated.php
│   └── OrderStatusChanged.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/Public/
│   │   ├── Api/Admin/
│   │   └── Web/Admin/
│   ├── Middleware/
│   │   ├── EnsureUserHasRole.php
│   │   └── ValidateTableSession.php
│   ├── Requests/
│   └── Resources/
├── Listeners/
│   └── LogOrderCreated.php
├── Models/
├── Policies/
├── Services/
│   ├── CartService.php
│   ├── OrderCreationService.php
│   ├── OrderStatusTransitionService.php
│   ├── QrCodeService.php
│   ├── TableSessionService.php
│   └── TokenGenerator.php
└── Support/
    └── MoneyCalculator.php

resources/js/
├── Layouts/
│   ├── AdminLayout.vue
│   └── CustomerLayout.vue
├── Pages/
│   ├── Admin/
│   ├── Customer/
│   └── Staff/
├── Components/
└── composables/

database/migrations/
database/seeders/
tests/Unit/
tests/Feature/
docs/ARCHITECTURE.md
```

---

## 14. Key MVP Decisions (Ambiguities Resolved)

| Ambiguity | Decision | Rationale |
|-----------|----------|-----------|
| Resume vs new session on re-scan | Resume active session if exists | Better UX for same party |
| Customer cancel order | Not in MVP | Spec doesn't require; staff can cancel pending |
| Real-time vs polling | **Polling every 5s** on staff/customer order views | No WebSocket infra delay; events ready for Reverb later |
| Cart storage | DB (`cart_items`) not session | Survives page refresh; auditable |
| Single restaurant resolution | `Restaurant::query()->where('status','active')->first()` via `RestaurantContext` service | No hard-coded name; future multi-tenant adds scope |
| Email verification | Disabled for staff MVP | Faster ops onboarding; Breeze verification optional |

---

## 15. Implementation Order

1. Database migrations & enums
2. Models & relationships
3. Factories & seeders
4. Extend User (role, restaurant_id) + auth
5. Policies & middleware
6. Services (token, QR, session, cart, order)
7. Public API + customer Inertia pages
8. Admin API + admin Inertia pages
9. Staff order board + polling
10. Events/listeners/logging
11. Tests
12. README & .env.example update

---

*Next step: Phase 3 implementation begins after architecture review.*
