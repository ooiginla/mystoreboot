# Storeboot Modular Architecture

Storeboot uses a custom `modules/` directory instead of a package-driven module system. Each module is a first-class Laravel boundary with its own provider, routes, migrations, models, actions, data objects, policies, events, and tests.

## Module Folder Contract

```txt
modules/{ModuleName}/
├── Actions/
├── Data/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Livewire/
├── Models/
├── Policies/
├── Providers/
├── Queries/
├── Services/
├── routes/
│   ├── admin.php
│   ├── api.php
│   └── storefront.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
└── tests/
```

## Loading Rules

- Module registration lives in `config/modules.php`.
- `App\Providers\ModuleServiceProvider` registers enabled module providers.
- `App\Support\Modules\ModuleServiceProvider` loads module routes, migrations, and views.
- Disabled modules remain in the codebase but are not loaded by Laravel.

## Route Rules

- Admin routes load under `/admin/{module}`.
- API routes load under `/api/{module}`.
- Storefront routes load without the admin prefix.

## Data Ownership

- `Tenancy` owns tenants and tenant context.
- `Access` owns membership, roles, and permissions.
- `Subscriptions` owns plans, modules, entitlements, and subscriptions.
- `Business` owns branches, departments, and business settings.
- Operational modules own their transactional tables.
- `Analytics` reads from operational modules and owns derived reporting tables later.

## Cross-Module Communication

Preferred order:

1. Public Action or Query class exposed by the owning module.
2. Domain event for side effects.
3. Read model or reporting table for analytics-heavy reads.
4. Direct Eloquent relationship only when the dependency is stable and intentional.
