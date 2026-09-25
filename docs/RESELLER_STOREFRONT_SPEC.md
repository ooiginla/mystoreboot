# Tenant Reseller Storefront — Product Specification

**Status:** MVP implemented  
**Module:** `Reseller`  
**Primary constraint:** A tenant operates either a standard product store or a reseller store, never both at the same time.

---

## 1. Purpose

The Reseller module lets a Storeboot tenant operate an online store without creating or
managing its own product catalogue. The tenant supplies one or more external supplier
website URLs. A scheduled recovery agent visits those websites once or twice daily,
recovers product information, and maintains the products displayed on the tenant's
Storeboot storefront.

The reseller tenant owns:

- its Storeboot storefront and branding;
- the customer relationship;
- the additional price charged over the recovered source price;
- checkout, customer payments, and customer-facing orders; and
- manual coordination with each supplier for fulfilment.

The source website remains the authority for source product information, price, and
availability. Supplier ordering, automatic supplier payments, and real-time stock
reservation are outside the first release.

This is a tenant feature, not a single global Storeboot marketplace. Every supplier,
recovered product, setting, order, and payment record is scoped to the active tenant.

---

## 2. Commerce modes

Every tenant has one active commerce mode:

| Mode | Product source | Admin operations | Customer orders |
|---|---|---|---|
| `standard` | Existing Storeboot catalogue | Existing Products, Inventory, Orders, and Payments | Existing order flow |
| `reseller` | Products recovered from configured supplier websites | Reseller menu | Reseller order flow |

Existing tenants default to `standard` so current behaviour does not change.

### 2.1 Module availability

- `Reseller Store` is a non-core, tenant-switchable module and defaults to off.
- A platform administrator enables it for a tenant from **Business Setup →
  Subscriptions → Module access**.
- The Reseller Setup/Reseller Store admin entry appears only while the module is enabled.
- Admin routes, storefront catalogue switching, and supplier recovery jobs enforce the
  module entitlement server-side; hiding the navigation entry is not the security boundary.
- Turning the module off retains reseller settings, suppliers, products, and orders so
  they are available again if the module is re-enabled.

### 2.2 Exclusivity rules

- A `standard` tenant cannot publish reseller products.
- A `reseller` tenant cannot create, edit, publish, or sell native Storeboot products.
- The admin navigation must show only the catalogue and order features appropriate to
  the active mode.
- Server-side authorization must enforce the mode. Hiding a menu item is not sufficient.
- Storefront queries must resolve products from only the active catalogue source.
- Records belonging to the inactive mode are retained and are never deleted during a
  mode change.

### 2.3 Changing mode

- A tenant with no orders may change mode after explicit confirmation.
- A tenant cannot change mode while it has an order awaiting payment, fulfilment,
  cancellation, or refund.
- Historical orders remain accessible in read-only form after a mode change.
- Previously published products are made unavailable when their mode becomes inactive.
- Changing mode must never migrate native products into reseller products, or reseller
  products into native products.

---

## 3. Tenant and storefront behaviour

A reseller tenant still requires an `OnlineStore`. It uses the existing tenant-owned
store settings for:

- store name, username, and domain;
- logo and favicon;
- theme, colours, typography, and homepage presentation;
- contact information and social links;
- delivery configuration;
- store policies; and
- enabled customer payment methods.

The normal tenant storefront URL remains the public entry point. A reseller tenant does
not need a global `/marketplace` URL. To the customer it is simply that tenant's online
store; only the source of its products and its order workflow differ.

The Reseller module owns separate product-detail, cart, checkout, and order-handling
logic, while reusing tenant visual settings through a stable read interface. Existing
standard-store behaviour must remain unchanged.

### 3.1 Storefront catalogue source

The storefront should depend on a catalogue-source contract rather than scattering
commerce-mode conditions throughout controllers and views.

```text
StorefrontCatalogSource
├── StandardCatalogSource  → existing products
└── ResellerCatalogSource  → recovered reseller products
```

At runtime, the tenant's commerce mode selects exactly one source.

---

## 4. Reseller admin menu

When a tenant is in `reseller` mode, the admin navigation contains:

```text
Reseller
├── Dashboard
├── Supplier Websites
├── Sourced Products
├── Orders
├── Payments
├── Settings
└── Scan Activity
```

### Dashboard

Shows active suppliers, visible products, unavailable products, last scan results, open
orders, revenue, payments, and estimated additional pricing earned.

### Supplier Websites

Adds, edits, pauses, scans, and removes supplier website configurations.

### Sourced Products

Shows recovered products and permits visibility control, exclusion, review, and manual
rescan. Product source data is not manually edited in the MVP.

### Orders

Shows only reseller orders and groups order items by supplier for manual fulfilment.

### Payments

Shows customer payments and refunds associated with reseller orders.

### Settings

Contains default pricing, recovery frequency, publishing behaviour, stale-product rules,
and customer-facing source attribution settings.

### Scan Activity

Shows the most recent run per supplier, products created or updated, errors, and next
scheduled run. Detailed runtime diagnostics may remain in application logs for the MVP.

---

## 5. Data model

All module tables must contain `tenant_id`, including child tables. Every unique key,
query, route binding, action, and authorization check must respect tenant scope.

The catalogue itself uses two main tables: suppliers and recovered products. Settings
and transactional tables are separate because catalogue data must not double as order or
payment history.

### 5.1 `reseller_settings`

One row per reseller tenant.

```text
id
tenant_id                         unique FK
pricing_mode                      percentage | fixed | combined
percentage_markup_basis_points    unsigned integer, default 0
fixed_markup_minor                unsigned bigint, default 0
auto_publish_products             boolean, default false
scan_frequency                    daily | twice_daily
show_source_store                 boolean, default true
stale_after_hours                 unsigned integer, default 36
hide_after_missing_scans          unsigned integer, default 3
created_at
updated_at
```

Money is stored in minor units. Percentages are stored in basis points so values such as
`12.50%` can be represented without floating-point arithmetic: `12.50% = 1250` basis
points.

### 5.2 `reseller_suppliers`

```text
id
tenant_id                         FK
name
website_url
logo_url                          nullable
contact_email                     nullable
contact_phone                     nullable
whatsapp                          nullable
scan_frequency                    nullable: inherit | daily | twice_daily
scan_configuration                nullable JSON
auto_publish_products             nullable boolean; null inherits tenant setting
is_active                         boolean
last_scanned_at                   nullable timestamp
last_scan_status                  never_run | running | successful | partial | failed
last_scan_message                 nullable text
created_at
updated_at

unique (tenant_id, website_url)
index  (tenant_id, is_active)
```

`scan_configuration` may contain starting catalogue URLs, excluded URL patterns, and
site-specific recovery instructions. It must never contain supplier login credentials
in plain text.

### 5.3 `reseller_products`

```text
id
tenant_id                         FK
supplier_id                       FK reseller_suppliers
product_url
source_product_reference          nullable
name
main_image_url                    nullable
source_price_minor                unsigned bigint
source_previous_price_minor       nullable unsigned bigint
selling_price_minor               unsigned bigint
selling_previous_price_minor      nullable unsigned bigint
currency_code                     ISO 4217 code
availability                      in_stock | out_of_stock | unknown
short_description                 nullable text
brand                             nullable
category                          nullable
sku                               nullable
variants                          nullable JSON
source_store_name
is_visible                        boolean
is_excluded                       boolean, default false
last_checked_at                   timestamp
missing_scan_count                unsigned integer, default 0
content_fingerprint               nullable string
created_at
updated_at

unique (tenant_id, supplier_id, product_url)
index  (tenant_id, is_visible, availability)
index  (tenant_id, supplier_id)
```

`source_store_name` is a recovered snapshot for display and diagnostics. The permanent
relationship is `supplier_id`; supplier names must not be used as foreign keys.

`variants` stores recovered variant information such as name, option values, source
price, availability, SKU, and source reference. Variant records may be normalized into a
separate table in a later version if reliable variant checkout requires it.

### 5.4 `reseller_orders`

```text
id
tenant_id                         FK
customer_id                       nullable FK
order_reference                   tenant-unique
customer_name
customer_email
customer_phone
delivery_address                  JSON
currency_code
subtotal_minor
delivery_minor
discount_minor
total_minor
payment_status                    pending | authorized | paid | partially_refunded | refunded | failed
order_status                      pending | confirmed | processing | completed | cancelled
fulfilment_status                 unfulfilled | partially_fulfilled | fulfilled
placed_at                         nullable timestamp
completed_at                      nullable timestamp
cancelled_at                      nullable timestamp
created_at
updated_at
```

### 5.5 `reseller_order_items`

An immutable commercial snapshot of what the customer purchased.

```text
id
tenant_id                         FK
reseller_order_id                 FK
reseller_product_id               nullable FK
supplier_id                       nullable FK
product_name
source_store_name
product_url
image_url                         nullable
sku                               nullable
variant                           nullable JSON
source_price_minor
percentage_markup_basis_points
fixed_markup_minor
unit_selling_price_minor
quantity
line_total_minor
fulfilment_status                 unfulfilled | supplier_notified | dispatched | delivered | cancelled
tracking_reference                nullable
tracking_url                      nullable
created_at
updated_at
```

Product, supplier, price, and markup snapshots must not change after checkout even if a
later recovery run changes or removes the source product.

### 5.6 `reseller_payments`

```text
id
tenant_id                         FK
reseller_order_id                 FK
provider
provider_reference
amount_minor
currency_code
type                              payment | refund
status                            pending | successful | failed
metadata                          nullable JSON
processed_at                      nullable timestamp
created_at
updated_at
```

The module should use Storeboot's common payment-provider integration contracts where
possible, but own its transaction records and expose them under the Reseller menu.

---

## 6. Reseller pricing settings

The reseller configures one default additional-pricing rule. It is applied whenever a
source product is created or its source price changes.

### 6.1 Supported pricing modes

#### Percentage

Adds a percentage of the recovered source price.

```text
source price = 10,000
percentage   = 15%
fixed amount = 0
selling price = 10,000 + 1,500 = 11,500
```

#### Fixed

Adds a fixed amount in the tenant's storefront currency.

```text
source price = 10,000
percentage   = 0%
fixed amount = 2,000
selling price = 10,000 + 2,000 = 12,000
```

#### Combined

Adds the percentage amount first and then the fixed amount.

```text
source price = 10,000
percentage   = 15%
fixed amount = 2,000
selling price = 10,000 + 1,500 + 2,000 = 13,500
```

### 6.2 Pricing formula

```text
percentage addition = round(source price × percentage basis points ÷ 10,000)

selling price = source price
              + applicable percentage addition
              + applicable fixed addition
```

- `percentage` mode ignores the fixed addition.
- `fixed` mode ignores the percentage addition.
- `combined` mode applies both additions.
- All calculations use integer minor units; binary floating-point money calculations are
  prohibited.
- A calculated selling price cannot be negative.
- The same rule is applied to `source_previous_price_minor` when generating a customer-
  facing previous price.
- A previous selling price is displayed only when it is greater than the current selling
  price.

### 6.3 Currency rule

The MVP accepts only supplier products whose recovered currency matches the tenant's
storefront currency. Mismatched-currency products are saved for review but are not
published. Automated currency conversion is deferred.

The fixed addition is always entered in the tenant's storefront currency.

### 6.4 Repricing behaviour

When settings change, the admin is shown a preview containing:

- number of affected products;
- sample old and new prices;
- lowest and highest resulting prices; and
- any products that cannot be repriced.

Saving settings queues repricing of all active, non-excluded reseller products for the
tenant. Existing orders and order items are never repriced.

When the recovery agent detects a new source price, it recalculates the selling price
using the current tenant pricing settings.

Per-supplier and per-product pricing overrides are deferred from the MVP. They may be
added later using the order of precedence:

```text
product override → supplier override → tenant default
```

### 6.5 Settings validation

- Percentage must be between `0.00%` and `500.00%`.
- Fixed addition must be zero or greater.
- `percentage` mode requires a percentage greater than zero.
- `fixed` mode requires a fixed addition greater than zero.
- `combined` mode requires both values greater than zero.
- Settings must show a calculated example before saving.

---

## 7. Product recovery agent

The agent operates per active supplier, inside the owning tenant's context.

### 7.1 Schedule

- `daily`: once every 24 hours.
- `twice_daily`: approximately every 12 hours.
- Jobs for different suppliers run independently.
- Only one recovery job may run for the same tenant and supplier at a time.
- Manual **Scan now** uses the same queued recovery process.

### 7.2 Recovery flow

1. Load the tenant, reseller settings, and supplier configuration.
2. Verify that the tenant is in `reseller` mode and the supplier is active.
3. Begin with the configured website and optional catalogue starting URLs.
4. Follow same-site catalogue, pagination, and product links within configured limits.
5. Identify product-detail pages.
6. Recover and validate the required product fields.
7. Upsert the product by tenant, supplier, and canonical product URL or stable source
   reference.
8. Calculate selling prices using the tenant's current pricing rule.
9. Increment the missing count for previously known products not recovered in this run.
10. Hide products once the configured missing-scan threshold is reached.
11. Record the supplier's scan result and schedule the next run.

### 7.3 Required recovered fields

1. Product URL
2. Product name
3. Main image
4. Current source price
5. Previous source price, when present
6. Availability
7. Short description
8. Brand
9. Category
10. SKU
11. Variants
12. Source store name
13. Last checked time

Product URL, name, current price, source store, currency, and last checked time are the
minimum fields required for automatic publication. Products missing any of these remain
hidden for review.

### 7.4 Update rules

- New products are published only when automatic publishing is enabled and required
  fields are valid.
- Products explicitly excluded by the reseller must never be recreated or republished
  automatically.
- A successful recovery resets `missing_scan_count` to zero.
- One missed scan does not hide or delete a product.
- At the configured threshold, the product becomes unavailable and hidden.
- Recovered products are never automatically hard-deleted.
- A content fingerprint may be used to avoid unnecessary writes when nothing changed.
- The agent must obey configured request limits and must not bypass authentication,
  access controls, or technical blocking.

### 7.5 Freshness

- Products older than `stale_after_hours` are visibly flagged in admin.
- Stale products may remain visible for browsing but cannot be purchased without a
  successful availability and price recheck.
- Product details show the customer-facing last-checked time where appropriate.

---

## 8. Storefront experience

The reseller storefront supports:

- homepage product sections;
- category browsing;
- product search;
- product cards;
- product details and variants;
- cart;
- checkout;
- order confirmation; and
- customer order tracking.

Each product detail page displays:

- product name and image;
- calculated reseller selling price;
- previous calculated price when it represents a genuine reduction;
- availability;
- short description, brand, category, SKU, and variants when available;
- source store attribution when enabled; and
- last checked time.

The storefront must never expose the reseller's markup configuration or source cost as
an internal field. Showing the public source price is a product decision controlled by
the tenant; it is not required in the MVP.

---

## 9. Checkout and orders

### 9.1 Checkout safety

Before payment, each cart item must be checked for freshness. A stale or unavailable
item blocks payment and asks the customer to refresh the cart.

Where a reliable live recheck cannot be completed, the MVP may create the order as
`pending` and require admin confirmation before payment is captured. The storefront must
clearly communicate this state to the customer.

### 9.2 Order creation

On successful checkout:

1. Create a tenant-scoped reseller order.
2. Snapshot every product, supplier, source price, markup, selling price, and selected
   variant into reseller order items.
3. Group items by supplier in the admin order view.
4. Record the payment attempt or successful payment.
5. Notify the reseller tenant.

### 9.3 Manual supplier fulfilment

The first release does not submit orders automatically to supplier websites. The admin
order view provides:

- supplier contact details;
- source product links;
- selected variants and quantities;
- customer delivery information;
- copyable supplier-order details;
- a WhatsApp contact action when available;
- **Mark supplier notified**;
- **Mark dispatched**;
- tracking reference and URL;
- **Mark delivered**; and
- cancellation and refund actions.

One customer order may contain items from multiple suppliers. Each item's fulfilment
status is managed separately, while the order derives its overall fulfilment status from
its items.

---

## 10. Payments

- The reseller tenant receives customer payments through its enabled Storeboot payment
  methods.
- Payments and refunds are shown under **Reseller → Payments**.
- Payment callbacks must resolve both tenant and reseller order safely.
- Provider references must be idempotent to prevent duplicate payment records.
- The MVP does not calculate or send supplier settlements automatically.
- Supplier costs, commissions, split payments, and payout schedules are deferred until
  Storeboot has explicit supplier commercial agreements.

---

## 11. Tenant isolation and authorization

- Every reseller query starts from the active tenant.
- IDs supplied in requests must never be trusted without tenant validation.
- Route-model binding must reject records owned by another tenant.
- Scheduled jobs carry both `tenant_id` and the target record ID.
- Queue workers establish tenant context before reading or writing data.
- Customer storefront URLs resolve the tenant before resolving a product or order.
- Payment callbacks validate provider signatures and resolve the expected tenant.
- Admin activity that changes suppliers, products, pricing, orders, or refunds is audited.

Suggested permissions:

```text
reseller.view
reseller.settings.manage
reseller.suppliers.manage
reseller.products.manage
reseller.scans.run
reseller.orders.manage
reseller.payments.view
reseller.refunds.manage
```

---

## 12. Onboarding

Tenant onboarding asks:

> How do you want to sell?

- **Sell my own products** — create and manage a normal Storeboot catalogue.
- **Build a reseller store** — add supplier websites and let Storeboot recover products.

The reseller path collects:

1. Store name and branding
2. First supplier website
3. Daily or twice-daily recovery
4. Percentage, fixed, or combined additional pricing
5. Payment configuration
6. Delivery and contact information
7. Social accounts
8. Review and launch

Optional steps provide **Skip for now**. A skipped supplier or pricing step leaves the
store unpublished until the minimum launch requirements are met.

---

## 13. Minimum launch requirements

A reseller storefront can be published only when:

- the tenant is in `reseller` mode;
- an Online Store record exists;
- reseller pricing settings are valid;
- at least one active supplier exists;
- at least one valid, visible, in-stock reseller product exists;
- at least one customer payment method is configured; and
- required store contact and policy information is present.

---

## 14. MVP scope

### Included

- Tenant-exclusive `standard` and `reseller` commerce modes
- Tenant-scoped reseller settings, suppliers, products, orders, items, and payments
- Separate Reseller admin navigation
- Supplier website registration
- Daily or twice-daily recovery jobs
- Automatic product creation and updating
- Automatic publishing option
- Percentage, fixed, and combined additional pricing
- Bulk repricing after a settings change
- Existing Online Store appearance and business settings
- Reseller storefront catalogue, cart, checkout, and order tracking
- Reseller-specific orders and payments pages
- Manual supplier fulfilment coordination
- Missing, stale, and excluded-product handling

### Explicitly deferred

- A global Storeboot marketplace
- Mixing native and reseller products in one tenant storefront
- Per-supplier or per-product markup overrides
- Automated currency conversion
- Real-time supplier inventory reservation
- Automatic purchasing from supplier websites
- Supplier accounts and dashboards
- Automatic supplier settlements or split payments
- Supplier commissions and contracts
- Automated returns with suppliers
- Normalized variant tables
- Guaranteed recovery from authenticated or technically blocked websites

---

## 15. Acceptance criteria

1. An existing tenant remains in `standard` mode and experiences no storefront or admin
   behaviour change.
2. A reseller tenant cannot access native product-management actions, including by direct
   URL.
3. A standard tenant cannot access reseller operations, including by direct URL.
4. A reseller tenant can add a supplier URL and schedule it daily or twice daily.
5. A recovery run can create and update tenant-scoped products using the required product
   fields.
6. One tenant cannot read, update, scan, order, or pay against another tenant's reseller
   records.
7. The storefront displays only visible reseller products belonging to the resolved
   tenant.
8. Percentage, fixed, and combined pricing produce the documented values using integer
   arithmetic.
9. Changing pricing settings recalculates active products but does not modify historical
   order items.
10. Repeatedly missing products are hidden according to the tenant setting and are not
    automatically deleted.
11. A reseller order snapshots source price, applied markup, selling price, supplier,
    product, and variant details.
12. Reseller orders and payments appear under the Reseller menu and not in standard-store
    operational screens.
13. The reseller can group an order by supplier and record notification, dispatch,
    tracking, and delivery.
14. A tenant cannot change commerce mode while active orders require action.
15. The implementation does not alter existing standard-store order or catalogue
    behaviour.

---

## 16. Open decisions before implementation

The following choices must be confirmed before development begins:

1. Whether reseller checkout captures payment immediately or first creates an order for
   manual availability confirmation.
2. Whether supplier/source attribution is mandatory or tenant-configurable.
3. Whether externally hosted product images may be displayed directly or must be cached
   after confirmed content permission.
4. The maximum pages and products recovered per supplier per run.
5. Whether the first successful scan requires approval before automatic publishing is
   enabled.
6. Whether delivery fees are entered by the reseller, recovered from suppliers, or
   deferred until order confirmation.

These decisions do not change the module boundary or the tenant-exclusive commerce-mode
design.
