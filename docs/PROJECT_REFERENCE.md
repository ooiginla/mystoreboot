# Storeboot Project Reference

Storeboot is a modular monolithic Laravel SaaS platform for micro and small enterprises. The product combines daily business operations, analytics, and eventually explainable recommendations so SME owners can understand performance and make better decisions.

## Product Positioning

Storeboot is a cloud-based ERP, POS, inventory, finance, and decision-support platform for retail shops, supermarkets, wholesalers, service businesses, pharmacies, restaurants, fashion stores, electronics stores, distributors, and multi-branch SMEs.

The first version should prioritize a reliable operational core. Analytics comes after operational data is captured consistently. The recommendation engine, advanced integrations, and offline POS are reserved for later phases.

## Technology Direction

- Backend/admin: Laravel 13+, Livewire/Volt, Flux, Tailwind.
- Customer-facing storefront: Laravel, Inertia, Vue, TypeScript, Tailwind in a separate module.
- Database: MySQL database named `storeboot`.
- Architecture: modular monolith with explicit module boundaries.
- Tenancy: single database with strict `tenant_id` scoping on tenant-owned data.
- Performance: Octane-compatible code, queues for slow work, eager loading to prevent N+1 queries.

## Module Map

| Module | Purpose | Initial Status |
| --- | --- | --- |
| Platform | Module registry, shared app concerns, global settings | Enabled |
| Tenancy | Tenants, tenant context, tenant isolation | Enabled |
| Access | Users, roles, permissions, invitations, activity logs | Enabled |
| Subscriptions | Plans, plan limits, module entitlements, tenant subscriptions | Enabled |
| Business | Business profile, branches, departments, settings | Enabled |
| Catalog | Products, services, categories, SKUs, variants, pricing | Enabled |
| Inventory | Stock tracking, movements, adjustments, transfers, low-stock alerts | Enabled |
| Sales | Sales, POS, invoices, receipts, payments, refunds, returns | Enabled |
| Customers | Customer records, CRM, complaints, follow-ups | Enabled |
| Procurement | Vendors, purchase orders, goods received, vendor payments | Enabled |
| Finance | Expenses, income, receivables, payables, P&L, cash flow | Enabled |
| Logistics | Delivery orders, dispatch, delivery partners | Future |
| Analytics | KPI dashboards and derived metrics | Enabled |
| Storefront | Public SME storefront and order enquiries | Future |
| Recommendations | Restock, sales, customer, expense, and vendor recommendations | Future |

## MVP Build Order

1. Platform, tenancy, access control, and subscriptions.
2. Business setup with branches and departments.
3. Catalog with products, services, categories, SKUs, and prices.
4. Inventory stock movements and low-stock alerts.
5. Sales, invoices, receipts, and payment tracking.
6. Customers, vendors, and purchase orders.
7. Expense tracking and basic profit/loss reporting.
8. Analytics dashboards.

## Engineering Guardrails

- Controllers and Livewire components stay thin.
- Business workflows live in `Actions`.
- Validated inputs are represented as DTOs in `Data`.
- Cross-module side effects should use events.
- Tenant-owned tables use `tenant_id`.
- Branch-specific operational tables use `branch_id`.
- High-frequency filters and joins must be indexed.
- No module should reach directly into another module's internals when an Action, Query, Event, or read model is more appropriate.

## UI Guardrails

- Admin interfaces should be efficient SaaS work surfaces, not marketing pages.
- Every page must include empty, loading, error, long-text, and mobile states.
- Use clear hierarchy, high contrast, visible focus states, and keyboard-friendly controls.
- Complex forms should use progressive disclosure.

## Deferred Scope

- Offline POS sync.
- Recommendation engine.
- Advanced accounting ledger.
- Payroll depth beyond basic staff/payroll records.
- WhatsApp, Instagram, Facebook, banking, tax, and payment integrations.
- Mobile app.
