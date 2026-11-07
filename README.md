# Laravel Shop

A single-store e-commerce site for the Indian market, built with Laravel 13, Livewire 4, Flux (free edition), Folio routing and Tailwind CSS.

- Storefront: home page, categories and brands, filtered and sorted listings, instant search, product pages with variants, reviews, pincode delivery check.
- Admin panel: products with variants and galleries, CSV import and export, inventory, orders, categories, brands, attributes, reviews, settings, activity log.
- Accounts: register, log in, email verification (optional), password reset, two-factor (TOTP) with recovery codes, Google sign-in.
- Money is stored as integer paise and displayed in rupees with Indian digit grouping.

Checkout, Razorpay payments and invoices are not built yet.

## Requirements

- PHP 8.3 or newer (8.4 is what the project targets), with the `gd`, `intl`, `fileinfo`, `zip`, `mbstring` and `pdo_sqlite` (or `pdo_mysql`) extensions
- Composer 2
- Node.js 20 or newer and npm
- SQLite (default, no server needed) or MySQL 8

## Install and run locally

```bash
git clone https://github.com/joydeep-bhowmik/laravel-shop.git
cd laravel-shop
git checkout phase-1-4-storefront-admin   # until merged to main

composer install
cp .env.example .env
php artisan key:generate

# SQLite (default): create the database file
touch database/database.sqlite

# Set the admin login before seeding (see "Admin login" below)
# ADMIN_EMAIL=admin@example.com
# ADMIN_PASSWORD=password

php artisan migrate --seed    # creates tables, roles, the admin account and demo catalog
npm install
npm run build                 # or: npm run dev while developing
php artisan serve
```

Open http://127.0.0.1:8000 for the store and http://127.0.0.1:8000/admin for the admin panel.

`composer run setup` covers the install steps (it copies `.env`, generates the key, runs migrations and builds assets). It does not create the SQLite file or seed the database, so on a first run follow the steps above and run `php artisan db:seed` if you skip the seed.

### Demo data

`migrate --seed` also loads a demo catalog (categories, brands, 62 products with variants, stock and generated images, customers, orders, coupons and banners). It runs only when `APP_ENV` is `local` or `testing`, and only on an empty database. To start over:

```bash
php artisan migrate:fresh --seed
```

## Admin login

The seeder creates one super admin from `ADMIN_EMAIL` and `ADMIN_PASSWORD`.

| Field    | Value               |
|----------|---------------------|
| Email    | `admin@example.com` |
| Password | `password`          |

These are local development values. Set `ADMIN_PASSWORD` in `.env` to your own value before seeding, or leave it blank to get a random password printed once in the seed output. Never use `password` on a public server.

### How to sign in to the admin panel

1. Start the app (`php artisan serve`) and open http://127.0.0.1:8000/login.
2. Enter `admin@example.com` and `password`, then click **Log in**.
3. Open http://127.0.0.1:8000/admin. Staff are sent here automatically after login.
4. Use the sidebar to manage products, inventory, orders, categories, brands, attributes, reviews, settings and the activity log.

The admin panel uses the same login page as customers. Admin pages are only shown to accounts with a staff role. A customer account that opens `/admin` gets a 403 error.

To change the password, sign in and open **Password and two-factor** from the account menu.

Other roles (`admin`, `manager`, `support`) are defined in `App\Enums\AdminRole`. Assign them to users with the role tools in the admin panel or with `User::assignRole()`.

## Configuration

Key values in `.env`:

| Key | Purpose |
|-----|---------|
| `DB_CONNECTION` | `sqlite` (default) or `mysql` |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Super admin created by the seeder |
| `SCOUT_DRIVER` | `database` (default). Search runs in the database, so no search service is needed |
| `QUEUE_CONNECTION` | `database` (default). Run `php artisan queue:work` for queued mail |
| `MAIL_*` | Mail server for verification and reset emails. `log` mailer writes them to `storage/logs` |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google sign-in. Create an OAuth client in Google Cloud Console with the redirect URI `APP_URL/auth/google/callback` |

Email verification is optional. Turn it on in admin Settings under "Accounts".

## Tests

```bash
php artisan test
```

The suite covers the services (money formatting, inventory, order status, settings encryption, product saving, CSV import), access rules for each admin role, the storefront pages, and the account and auth flows. Tests run on an in-memory SQLite database.

Code style: `vendor/bin/pint`.

## Project layout

- `resources/views/pages/` - Folio pages. Storefront pages sit at the top level. Admin pages are in `pages/admin/` and require the `admin.access` permission.
- `resources/views/components/` - Livewire single-file components (`store/`, `admin/`, `auth/`, `account/`).
- `resources/views/layouts/app.blade.php` - site layout (header, category navigation, footer).
- `resources/views/layouts/admin.blade.php` - admin layout (sidebar).
- `app/Services/` - business logic: catalog saving, inventory, order status, settings, storefront listing and caching, reviews, delivery checks, two-factor.
- `app/Enums/` - statuses, roles and permissions.

## Known limitations

- Razorpay payments, cart, checkout, COD orders and GST invoices are not built yet (Phase 5).
- Two-factor setup shows the secret key and an `otpauth://` link; there is no QR code.
- Admin pages for customers, coupons, banners and roles are not built yet.
