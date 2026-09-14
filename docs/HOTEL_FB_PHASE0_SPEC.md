# Phase 0 — Foundations: Build Spec

**Parent:** `HOTEL_FB_IMPLEMENTATION_PLAN.md`
**Date:** 2026-09-05
**Status:** Spec for review — no code until approved.

Phase 0 is pure enabling plumbing. It ships no hotel *feature* on its own; it makes
Phases 1–6 possible and immediately improves the existing retail flows (sell/stock
in kg, litres, packs; deplete from the right store). It is also the **riskiest**
phase — the decimal change touches every quantity in the system — so it is
specified precisely and delivered behind a heavy test pass.

Three workstreams:

- **A. Units of measure & conversions**
- **B. Decimal quantities** (the wide-blast-radius migration)
- **C. Location model** (the three-concept separation the F&B use case demands)

---

## Guiding constraint — Basic tenants must not be complicated

Phase 0 is additive and **invisible to a plain retail / online-store tenant**. This
is a build requirement, not a nice-to-have:

- **Default unit is "each".** Every existing product backfills to `base_unit_id = ea`
  (1 = 1 item, today's behaviour). The unit picker and any unit beyond "each" are
  **gated behind the F&B / advanced-inventory feature** (subscription-module gating).
  A basic seller never sees a UoM control.
- **Quantities display and input as whole numbers** for "each" products — storefront
  steppers stay `step=1`; decimals only unlock for weight/volume products (which a
  basic store doesn't have). Display trims trailing zeros (`2.0000` → `2`), so the
  online store shopper sees no change.
- **One branch = zero setup.** Prep stations, sales points, and per-line locations
  are optional/nullable. A basic tenant has one branch → one location → one default
  sales point (auto-created by backfill); the `LocationResolver` falls back to it.
  Arms/stations never appear.
- **Feature gate = a subscription module.** All advanced UI (units beyond "each",
  prep stations, and later recipes/production/restaurant POS) is gated behind a new
  **billable module** (proposed key `fnb`, label "F&B / Advanced Inventory"), using
  the existing module-gating stack — not a per-business toggle. "The plumbing
  exists" must not become "the plumbing clutters the screen."
- The only tenant-wide effect is the internal decimal storage change
  (`2` → `2.0000`), which has no visible impact.

### Feature gate — subscription module wiring

Reuse the existing subscription plumbing exactly as other modules do:

- Register a new row in `billable_modules` (key `fnb`), **not core**, default off.
- Add it to the plan(s) that should include it via `plan_module_entitlements`
  (recommend: Enterprise), seeded in `StorebootPlanSeeder`.
- Gate every advanced menu item, route, and Blade partial with the existing check
  (`TenantModuleAccess::allows('fnb')` / `@if($hasTenantModule('fnb'))`), same
  pattern as `storefront`/`analytics`.
- Per-tenant `TenantModuleEntitlement` overrides still apply (a tenant can be granted
  `fnb` off-plan, e.g. a pilot).
- When `fnb` is **off**: unit picker hidden (products behave as "each"), no
  prep-station/stations menus, single-location resolution — i.e. today's basic
  experience. When **on**: the advanced controls appear. The decimal columns and the
  `LocationResolver` are always present regardless (they're invisible plumbing); only
  the *UI and configuration surfaces* are gated.

## 0. Domain model this phase locks in

The driving use case (confirmed with the operator): **one restaurant, several
"arms" — grill, hot kitchen, flour kitchen, bar — each with its own products and
its own inventory store, but one receptionist/cashier takes one order and issues
one bill to one customer.** A single order's lines fan out to different arms, and
each line depletes stock from *that arm's* store. The stricter "the lounge has its
own separate bill" case must still be possible, but it is the exception.

The old analysis (and most retail POS) collapse three ideas into one ("outlet =
location = bill"). Phase 0 separates them permanently:

| Concept | What it is | Table | Cardinality on an order |
|--------|-----------|-------|------------------------|
| **Inventory location (store)** | Where stock physically lives and depletes | `inventory_locations` (extended) | many per order (one per line) |
| **Prep station (arm)** | Grill / hot kitchen / flour kitchen / bar. Routes a line and points at a default store | `prep_stations` (new) | many per order |
| **Sales/billing point (till)** | Where the customer is served & billed. One order = one bill | `sales_points` (new, light) | exactly one per order |

**Key resolution rule** (the reason line-level location matters):
a sales-order **line** depletes from
`line.inventory_location_id`
→ else `product.prep_station.default_location_id`
→ else `sales_point.default_location_id`
→ else error.

Phase 0 **adds the columns and the resolver**; it does **not** yet change POS UI or
actually deplete per-line (that lands in Phase 1). This keeps Phase 0 to data
foundations while guaranteeing Phase 1 has everything it needs.

---

## A. Units of measure & conversions — ✅ BUILT (2026-09-05)

> **Design note (deviation from original draft):** the separate `unit_conversions`
> table was dropped in favour of a `to_base_factor` column on each unit. Universal
> same-dimension conversion is `qty × from.to_base_factor ÷ to.to_base_factor`;
> product-specific packs (carton→base) use the variant's `purchase_to_base_factor`.
> Fewer moving parts, same capability. Units with a null factor (pack/carton/box)
> are product-specific and cannot be converted universally.
>
> **Delivered:** `UnitDimension` enum; `units_of_measure` table + `UnitOfMeasure`
> model; `EnsureDefaultUnitsAction` (seeds ea/g/kg/ml/L/pack/carton/box, wired into
> tenant registration); `UnitConverter` support class; `base_unit_id` /
> `purchase_unit_id` / `purchase_to_base_factor` on `product_variants` (+ model
> relations); backfill migration (every existing variant → "each", 34/34 done); the
> `fnb` billable module installed and attached to Enterprise + trial only.
> Verified: conversions (1.5 kg→1500 g, 250 g→0.25 kg, 2 L→2000 ml), cross-dimension
> and pack rejections, and that `fnb` is off for Starter.

### A.1 Tables

**`units_of_measure`** (tenant-scoped)
- `id`, `tenant_id`
- `code` (e.g. `kg`, `g`, `L`, `ml`, `ea`, `pack`) — unique per tenant
- `name`
- `dimension` — enum: `count` | `weight` | `volume` | `length`
- `is_base_for_dimension` (bool) — the canonical unit stock is stored in for that dimension
- `status`, timestamps

**`unit_conversions`** (tenant-scoped, universal within a dimension)
- `from_unit_id`, `to_unit_id`, `factor` `decimal(18,6)`
- constraint: both units share a `dimension`
- e.g. `1 kg = 1000 g` → factor 1000

**Product-specific pack conversions** (where a pack size is product-specific, not
universal — "1 carton of *this* beer = 24 bottles"):
add to `product_variants`:
- `base_unit_id` (FK `units_of_measure`, nullable during rollout → required for tracked products)
- `purchase_unit_id` (nullable; the unit POs are placed in)
- `purchase_to_base_factor` `decimal(18,6)` (nullable; how many base units per purchase unit)

Universal conversions live in `unit_conversions`; product-specific pack sizes live
on the variant. The resolver prefers the variant override, falls back to
`unit_conversions`.

### A.2 Storage convention

- **All stock is stored in the product's `base_unit`.** Movements, stock levels,
  batches — always base units.
- Purchases entered in `purchase_unit` are converted to base on receipt.
- Recipes (Phase 1) enter quantities in any unit of the right dimension; converted
  to base for depletion.
- Sales enter in the sales unit (usually base); converted to base for depletion.

### A.3 Seeding & backfill

- `EnsureDefaultUnitsAction` seeds a starter set per tenant on demand
  (`ea`, `kg`, `g`, `L`, `ml`, `pack`, `carton`) plus the standard conversions.
- Backfill: every existing tracked product gets `base_unit_id = ea` (each). This is
  the correct no-op default — today's integer counts are "each".

### A.4 Support class

`UnitConverter` — `convert(qty, fromUnitId, toUnitId, ?variant): float`, throws on
cross-dimension conversion, rounds per the discipline in §B.2. Single source of
truth; every action calls it, no ad-hoc factors.

---

## B. Decimal quantities — ✅ BUILT (2026-09-05, storage + safety; entry deferred)

> **Scope delivered:** the system is now decimal-*capable* and decimal-*safe*, with
> decimal *entry* still gated off (validation unchanged) so it turns on per-feature
> in Phase 1 and stays behind `fnb`.
>
> - All 13 columns migrated to `decimal(15,4)` across Inventory/Procurement/Sales
>   (3 migrations, reversible). Existing integers became `n.0000`.
> - `decimal:4` casts on the 7 quantity-bearing models; quantity accessors
>   (`quantity_available`, `quantity_returnable`, `quantity_pending`) now return
>   float; `stock_value_minor` rounds to int.
> - `Quantity` support helper (`round()` to 4 dp, `format()` trims trailing zeros).
> - Core write/compute paths made decimal-safe — quantities carried as floats, money
>   rounded to minor units at each boundary: `PostInventoryMovementAction`,
>   `AdjustInventoryReservationAction` (reserve/release now float),
>   `CreateSalesOrderAction`, `CompleteSalesOrderAction`, `ProcessSalesReturnAction`,
>   `ExpireOnlineOrderReservationsAction`, `ReceivePurchaseOrderAction`,
>   `SavePurchaseOrderAction`, `InventoryController`, plus report closures in
>   `FinanceReportController` and `Analytics/DashboardController` (were typed `:int`).
> - Visible stock displays in the inventory view use `Quantity::format`.
>
> **Verified:** full suite 162/166 green; every test touching these paths passes
> (Inventory/Procurement/Sales/Finance/Analytics/Storefront). The 4 remaining reds
> are pre-existing and unrelated (removed manual-settlement routes; starter-plan
> module list; onboarding redirect — all from earlier sessions).
>
> **Deliberately deferred to Phase 1 (documented, safe with integer data):**
> - Quantity *validation* stays `integer` (decimal entry gated to Phase 1 per feature).
> - Storefront checkout keeps whole-unit quantities (online sales are whole units).
> - Display-only truncation in `product-dialog`/`product-card` stock sums and some
>   Finance report totals (correct for whole numbers; refine when decimals flow).

### B.1 Exact columns to migrate `integer → decimal(15,4)`

| Table | Columns |
|-------|---------|
| `inventory_stock_levels` | `quantity_on_hand`, `quantity_reserved`, `reorder_level`, `reorder_quantity` |
| `inventory_batches` | `quantity_remaining` |
| `inventory_movements` | `quantity`, `stock_after` |
| `purchase_order_items` | `quantity_ordered`, `quantity_received` |
| `goods_receipt_items` | `quantity_received` |
| `sales_order_items` | `quantity`, `quantity_returned` |
| `sales_return_items` | `quantity` |

One migration per module (Inventory, Procurement, Sales) so module ownership stays
clean; each has a `down()` restoring `integer`. `decimal(15,4)` holds up to
99,999,999,999.9999 — ample. Changing column type preserves data (existing integers
become `n.0000`); no data migration needed. **Deploy:** production data is small
(< ~1,000 rows in the affected tables) so a plain maintenance-window `ALTER` is
fine — no online-DDL/copy-and-swap. Brief downtime is acceptable.

### B.2 Rounding discipline (hard rule for the whole codebase)

1. **Quantities** round to **4 dp** at each persisted movement (`round($q, 4)`).
2. **Money stays in minor units (integer).** Any `qty × unit_cost` computes cost,
   then rounds to the **currency minor unit** before persisting — never store
   fractional kobo.
3. Use string/`bcmath`-safe math where a chain of conversions could drift; a single
   `round()` at the persistence boundary is sufficient for one-step conversions.
4. Weighted-average cost recompute (`average_cost_minor`) keeps its current
   minor-unit integer form; only the *quantity* side becomes decimal.

### B.3 Code audit — every touchpoint that assumes integer quantities

Each must be reviewed for `(int)` casts, integer arithmetic, and display
formatting. Confirmed call sites:

- `modules/Inventory/Actions/PostInventoryMovementAction.php` — `quantity`,
  `stock_after`, weighted-average recompute, batch `quantity_remaining`.
- `modules/Inventory/Actions/AdjustInventoryReservationAction.php` — reserved qty.
- `modules/Sales/Actions/CreateSalesOrderAction.php` — line qty, reservation, POS
  stock-out.
- `modules/Sales/Actions/CompleteSalesOrderAction.php` — depletion qty, COGS.
- `modules/Sales/Actions/ProcessSalesReturnAction.php` — restock qty.
- `modules/Procurement/Actions/ReceivePurchaseOrderAction.php` — received qty,
  landed-cost apportionment.
- `modules/Storefront/Http/Controllers/StorefrontController.php` — checkout qty &
  reservation loop.
- **Requests/validation:** every `integer` rule on a quantity field →
  `numeric|min:0` with a `decimal:0,4` guard (`InventoryMovementRequest`,
  `ProductRequest`, sales/PO/return requests, storefront cart).
- **Blade:** every `number_format($qty)` on a quantity → format with up to 4 dp,
  trailing-zero-trimmed (a `qty()` helper/`@qty` directive so display is uniform).
  Grep target: `number_format(` across `modules/**/resources/views`.
- **Casts:** add `'quantity' => 'decimal:4'` (etc.) on the affected models
  (`InventoryStockLevel`, `InventoryMovement`, `InventoryBatch`, sales/PO/return
  item models).
- **Tests:** `InventoryOpeningStockTest` and any test asserting integer stock.

**Deliverable of the audit:** a checklist PR where each file above is ticked with
its rounding-boundary note. Nothing merges until the list is complete.

---

## C. Location model — ✅ BUILT (2026-09-05)

> **Delivered:** `location_types` lookup (tenant-configurable, seeded from the system
> enum) + `LocationType` model + `EnsureLocationTypesAction`; `inventory_locations`
> extended with `parent_id`, `replenishment_source_location_id`, `is_sellable_point`
> (+ `parent`/`children`/`replenishmentSource` relations); `prep_stations` +
> `PrepStation` model; `sales_points` + `SalesPoint` model + `EnsureSalesPointsAction`
> (one default sales point per branch → branch store); `products.prep_station_id`
> (+ `prepStation` relation); `sales_order_items.inventory_location_id`
> (+ `inventoryLocation` relation); `LocationResolver` support class; backfill
> migration; registration wiring for location types + sales points.
>
> **Verified:** location types seeded (4), one sales point per branch pointing at the
> branch store, resolver chain (explicit → prep-station store → sales-point store →
> throw), and 47 Inventory/Catalog/Sales tests still green (616 assertions). Reno
> test data restored.
>
> **Deliberately deferred (per Phase 0 scope):** the POS still reads
> `$posLocations->first()`; rewiring it to the sales point's `default_location_id`
> and actually depleting per line happens when Phase 1 *consumes* these columns. The
> plumbing (columns + resolver) is in place and idle.

### C.1 Extend `inventory_locations`

Add:
- `parent_id` (self-FK, nullable) — hierarchy: Central Store → Kitchen Store → Line Fridge.
- `is_sellable_point` (bool, default false) — can a sale deplete directly here.
- Make `location_type` **tenant-configurable** rather than a fixed enum:
  introduce `location_types` lookup (tenant-scoped: `key`, `label`, `is_system`),
  seed it from today's four (`branch`, `warehouse`, `store_room`, `service_unit`)
  so nothing breaks, and let tenants add their own (e.g. `kitchen_store`,
  `bar_store`). `inventory_locations.location_type` keeps its string value; the
  lookup drives the UI list. Existing `InventoryLocationType` enum stays as the
  seed/system set.

### C.2 New: `prep_stations` (arms)

- `id`, `tenant_id`, `branch_id` (nullable)
- `name` (Grill, Hot Kitchen, Flour Kitchen, Bar)
- `default_location_id` (FK `inventory_locations`) — the arm's store
- `status`, timestamps

Assign products to an arm:
- add `prep_station_id` (nullable FK) to `products` (or `product_variants` if a
  variant can be made by a different arm — **recommend product-level** for
  simplicity; revisit only if a real variant-splits-arms case appears).

### C.3 New: `sales_points` (tills / billing points)

Light table so an order knows its bill origin and a fallback store:
- `id`, `tenant_id`, `branch_id`
- `name` (Reception, Lounge Till)
- `default_location_id` (FK `inventory_locations`) — fallback depletion store &
  today's "first location" replacement
- `status`, timestamps

Replaces the current `$posLocations->first()` behaviour: a till resolves to its
configured `default_location_id`.

### C.4 Per-line depletion location column

- Add `inventory_location_id` (nullable FK) to `sales_order_items` and to the
  storefront line equivalent.
- Add a `LocationResolver` support class implementing the §0 resolution rule.
- **Phase 0 populates and stores it; Phase 1 consumes it** for actual multi-store
  depletion. In Phase 0 the existing single-location depletion keeps working
  (order-level location = the till's `default_location_id`).

### C.5 Replenishment source (for Phase 3, defined now)

- Add `replenishment_source_location_id` (nullable self-FK) to
  `inventory_locations` — each arm store's default source for requisitions/par.
  Defined in Phase 0 so the hierarchy is complete; consumed in Phase 3.

- **Design note — single default vs prioritized list (Phase 3 decision):** this is
  a *non-binding default* only. A transfer/requisition can source from ANY store, so
  a store can always pull from an alternate when its default source is empty — the
  requisition's own source picker handles that. What the single field cannot do is
  let the system *automatically* know a fallback order. If auto-suggested fallback
  ("try Central, then Warehouse") is wanted, upgrade in Phase 3 to a
  `location_replenishment_sources` pivot (`location_id`, `source_location_id`,
  `priority`). Recommendation: keep the single default now; decide the pivot in
  Phase 3 based on whether automatic fallback is actually needed (manual source
  selection covers the empty-store case already).

### C.6 Backfill

- One `inventory_locations` row already exists per branch (existing backfill). Set
  `parent_id = null` for those (they become top-level).
- Create one default `sales_point` per branch pointing at that branch's existing
  location, so current POS behaviour is preserved exactly.
- `prep_station_id` on products stays null (single-arm tenants never need it; the
  resolver falls back to the till's location).

---

## D. Delivery order

1. **A — Units of measure** (additive; no behaviour change). Seed + backfill `ea`.
2. **C — Location model** (additive columns + new tables + resolver; behaviour
   unchanged because resolver falls back to till default = today's location).
3. **B — Decimal migration** last, once A/C are stable, because it's the risky one
   and benefits from the audit checklist being the only moving part.

Each step is independently deployable and reversible.

## E. Testing strategy

- **Decimal:** unit tests posting `0.25`, `1.5`, `0.001` movements; weighted-average
  cost with fractional receipts; a full receive→sell→return cycle asserting stock
  and COGS to 4 dp and money to minor units. Regression-run the existing inventory
  and sales suites unchanged (integer values must still pass as `n.0000`).
- **UoM:** convert kg↔g, L↔ml, reject count↔weight; product pack override
  (carton→bottle) on receipt.
- **Location:** resolver returns line → product-station → sales-point → error in
  that order; existing single-location POS path unchanged.

## F. Explicitly NOT in Phase 0

- No recipe/BOM, no `stock_policy`, no sale-time multi-store depletion *behaviour*
  (columns only) — **Phase 1**.
- No production orders — **Phase 2**.
- No requisition/par consumption — **Phase 3** (source column defined only).
- No KOT/KDS or station-routing UI — **Phase 6**.
- No POS redesign; the till just reads its configured default location instead of
  "first location".

---

## G. Settled build decisions (2026-09-05)

1. **Arm assignment: product level.** `prep_station_id` lives on `products`, not
   `product_variants`. No known case of one product's variants being made by
   different arms; can be promoted to variant-level later without a breaking change.
2. **Migration: plain maintenance-window `ALTER`s.** Production data is tiny
   (< ~1,000 rows in the affected tables) and brief downtime is acceptable — no
   online-DDL or copy-and-swap needed, including for `inventory_movements`.
3. **Reorder fields go decimal too.** `reorder_level` and `reorder_quantity` are in
   the §B.1 migration list, for consistency with decimal stock.
