# تدلّل (Tedallal)

Smart Table Service Platform — MVP by Troy Agency

A production-oriented Laravel + Vue 3 platform that lets restaurant customers scan a table QR code, browse the menu, order from their phone, and lets staff manage orders in real time.

## Stack

- **Backend:** Laravel 13, PHP 8.3, PostgreSQL (SQLite supported for local dev)
- **Frontend:** Vue 3, TypeScript, Inertia.js, Tailwind CSS
- **Auth:** Laravel Breeze (staff/admin), anonymous table sessions (customers)
- **API:** Laravel Sanctum + REST JSON endpoints

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full design document.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL 14+ (recommended) or SQLite

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

## Environment

Key variables in `.env`:

```env
APP_NAME="تدلّل"
APP_URL=http://localhost:8000
APP_LOCALE=ar

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tedallal
DB_USERNAME=postgres
DB_PASSWORD=

FILESYSTEM_DISK=public
```

## Demo credentials

After seeding (`php artisan migrate:fresh --seed`), default password for all demo accounts is `password`:

| Role / Scope | Email | Password | Details |
|---|---|---|---|
| **Super Admin (Owner)** | `super@tedallal.test` | `password` | Platform Owner, exclusive `toggle_maintenance` permission |
| **System Admin** | `admin@tedallal.test` | `password` | Platform Manager (restricted from maintenance toggle) |
| **Restaurant Admin** | `admin@bait-alsham.test` | `password` | Bait Al-Sham Restaurant Admin (tables, menu, staff, settings) |
| **Cashier (Staff)** | `cashier@bait-alsham.test` | `password` | Bait Al-Sham Staff (kitchen/orders board, view menu) |
| **Waiter (Staff)** | `waiter@bait-alsham.test` | `password` | Bait Al-Sham Staff (orders board) |

## Running locally

```bash
composer dev
```

Or separately:

```bash
php artisan serve
npm run dev
```

## Real-time orders (WebSockets)

The app uses **Laravel Reverb** for instant order notifications.

```bash
composer dev   # starts server, queue, reverb, vite together
```

Or run Reverb separately:

```bash
php artisan reverb:start
```

Staff/admin order boards update instantly when:
- A new order is placed (`order.created`)
- Order status changes (`order.status_changed`)

Customer order tracking also receives live status updates on the public `order.{orderNumber}` channel, with polling as fallback.

## Print all QR codes

Admin → **Tables** → **طباعة كل رموز QR** (`/admin/tables/print`)

Opens a print-optimized layout with restaurant name, table name, QR code, and "امسح للطلب".

## Restaurant settings

Admin → **Settings** (`/admin/settings`) — update name, phone, address, and currency.


1. Seed the database — tables get QR codes automatically
2. Open admin → **Tables** or visit `/admin/tables`
3. Scan QR or open `/t/{public_token}` in a mobile browser
4. Browse menu → add to cart → confirm order → track status

## Admin routes

- `/admin/dashboard` — metrics
- `/admin/orders` — order management
- `/admin/tables` — tables & QR codes
- `/admin/menu/categories` — menu categories
- `/admin/menu/items` — menu items

Staff: `/staff/orders`

## Testing

```bash
php artisan test
```

Covers QR resolution, order creation, price snapshots, authorization, and domain logic.

## Security notes

- Table QR uses cryptographically random `public_token` (never expose numeric IDs)
- Customer sessions use httpOnly cookies + optional `X-Session-Token` header
- Order prices are calculated server-side; historical orders store price snapshots
- Staff cannot access admin-only routes (tables, menu, users)

## Database relationships

```
Restaurant → Tables → TableSessions → Orders → OrderItems
Restaurant → MenuCategories → MenuItems
TableSession → CartItems
```

## Production deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`
2. Configure PostgreSQL and Redis (optional, for queues/broadcasting)
3. Run `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`
4. Serve with Nginx/Apache; enable HTTPS for secure session cookies
5. Point `FILESYSTEM_DISK` to S3 or equivalent for images/QR storage

---

Built for expansion to multi-restaurant SaaS without rewriting core domain logic.
