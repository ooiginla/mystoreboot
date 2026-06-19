# Storeboot

Storeboot is a modular monolithic SaaS platform for micro and small enterprises. It is designed to help SMEs manage daily operations, understand business performance, and eventually receive explainable recommendations for better decisions.

The current build focuses on the cloud admin backend and the operational foundation: tenancy, business setup, branches, departments, roles, users, and subscription-plan module access.

## Product Vision

Storeboot helps businesses move from paper records, WhatsApp messages, notebooks, spreadsheets, and disconnected apps into one structured platform.

Target users include:

- Retail shops
- Supermarkets
- Wholesalers
- Service businesses
- Restaurants and food businesses
- Pharmacies
- Fashion stores
- Electronics stores
- Multi-branch SMEs

## Tech Stack

- PHP 8.3+
- Laravel 13+
- MySQL
- Laravel Blade for the current admin foundation
- Planned admin UI stack: Livewire / Volt, Flux, Tailwind
- Planned customer-facing module: Laravel, Inertia, Vue, TypeScript, Tailwind

## Architecture

Storeboot is built as a modular monolith.

Modules live in `modules/` and are loaded through a custom module registry:

- `config/modules.php`
- `app/Providers/ModuleServiceProvider.php`
- `app/Support/Modules/ModuleServiceProvider.php`

Core modules currently scaffolded:

- Platform
- Tenancy
- Access
- Subscriptions
- Business
- Catalog
- Inventory
- Sales
- Customers
- Procurement
- Finance
- Logistics
- Analytics
- Storefront
- Recommendations

Detailed architecture notes are in:

- `docs/PROJECT_REFERENCE.md`
- `docs/MODULAR_ARCHITECTURE.md`
- `docs/STATUS_REFERENCE.md`

## Current Features

Implemented foundation:

- Login and logout
- Platform super admin flag
- Tenant-aware admin access
- Business profile setup
- Business type setup
- Branch/store setup
- Department/unit setup
- User roles
- Organization users through tenant memberships
- Platform-admin-only organization directory
- Subscription plans and module entitlement seed data

Important tenancy model:

- `users` are global login identities.
- Organizations are represented by `tenants`.
- Organization-specific users are represented by `tenant_memberships`.
- This allows one user to belong to one or many organizations without duplicating login accounts.

## Local Setup

Install dependencies:

```bash
composer install
```

Copy and configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=storeboot
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the local server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://127.0.0.1:8000
```

## Default Development Admin

The current local super admin account is:

```text
Email: iginla.omotayo@gmail.com
Password: francis
```

This account is intended for local development only.

## Testing

Run the test suite:

```bash
php artisan test
```

Format PHP code:

```bash
vendor/bin/pint
```

Compile Blade views:

```bash
php artisan view:cache
```

## Development Notes

- Keep controllers thin.
- Put business workflows in module `Actions`.
- Put validation in module `Http/Requests`.
- Tenant-owned records should be scoped by `tenant_id`.
- Branch-specific records should include `branch_id` where applicable.
- Platform-admin views must not leak into tenant-user workflows.
- Tenant users must only access organizations they belong to through `tenant_memberships`.

## Known Local Environment Note

The local PHP installation may show a warning about a missing `swoole.so` extension. The warning is from local PHP configuration and does not currently block the Laravel app. It should be cleaned up before Laravel Octane work begins.

## Roadmap

Near-term modules:

- Product and service catalog
- Inventory stock tracking
- Sales and invoicing
- Customers and vendors
- Basic finance
- Analytics dashboard

Deferred modules:

- Recommendation engine
- Offline POS sync
- Advanced accounting
- Payroll depth
- Storefront module
- External integrations
