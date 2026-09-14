# Raw Materials — Separation Spec

**Parent:** `HOTEL_FB_IMPLEMENTATION_PLAN.md`
**Date:** 2026-09-06
**Status:** ✅ BUILT via **Option A** (2026-09-06). Full suite 174/174. See `project_storeboot_hotel_fb` memory for the implementation summary.

## 1. Goal

Give raw materials (ingredients / inputs like rice, oil, flour, packaging) a
**first-class identity separate from sellable products**:

- Their own **catalog and management screen** — not mixed into Products & Services.
- **Never appear** on the storefront, the POS, or sellable product listings.
- Still fully **stockable** (received, counted, transferred, requisitioned) and
  **usable in recipes/production** and **purchased from vendors**.
- Carry a **measurement category** (units), like products.

## 2. Where products and variants are coupled today (grounded in code)

Everything that holds stock or moves it is keyed by **`product_variant_id`**:

| Area | Table(s) | Key |
|------|----------|-----|
| Stock | `inventory_stock_levels`, `inventory_batches`, `inventory_movements` | `product_variant_id` |
| Recipes | `recipes.output_product_variant_id`, `recipe_items.component_product_variant_id` | variant |
| Production | `production_orders.output_product_variant_id`, `production_order_items.component_product_variant_id` | variant |
| Requisitions | `stock_requisition_items.product_variant_id` | variant |
| Purchasing | `purchase_order_items.product_variant_id`, `goods_receipt_items` (via PO item) | variant |
| Sales | `sales_order_items.product_variant_id`, `sales_return_items` (via order item) | variant |

Sellable surfaces already filter by product type: the storefront and POS only load
`product_type = Product`. So **anything that is not a `Product` type is already
excluded from being sold** — this is the seam we exploit.

## 3. The central tension

Raw materials must be **stocked**, and all stock is keyed by `product_variant_id`.
So "separate raw materials" forces a choice about the **inventory core**:

- Either raw materials keep a `product_variant` identity (so inventory is untouched),
- Or inventory becomes **polymorphic** (stock can belong to a variant *or* a raw
  material), which rewrites the most central, heavily-tested part of the system.

This single fact drives the options below.

---

## 4. Options

### Option A — Type-based separation *(recommended)*

Raw materials are products of a **new `ProductType::RawMaterial`**, each with a
variant (as today), but presented and managed as a **separate catalog**.

- **New:** `ProductType::RawMaterial` case; a **Raw Materials** admin area
  (`admin.raw-materials.*`) — its own list, create/edit, stock view — reusing the
  catalog/inventory machinery but scoped to `product_type = raw_material`.
- **Excluded from selling:** storefront/POS/sellable lists already filter to
  `Product`; raw materials are simply never `Product`, so they never appear. Add a
  guard so a `raw_material` can't be added to a sales order.
- **Recipes/production:** `recipe_items` / `production_order_items` reference raw
  materials' variants (already do — ingredients are variants today). The ingredient
  picker is scoped to raw materials (+ produced goods for sub-recipes).
- **Purchasing / requisitions / stock:** unchanged — they already key by variant.
- **Measurement category:** already on `products`; applies to raw materials too.
- **Migration:** optionally reclassify existing ingredient products (products used
  only as recipe components, never sold) to `raw_material` — offered as a reviewed,
  reversible data step, not automatic.

**Pros:** delivers the entire user-facing goal (distinct catalog, separate
management, never sold, categorized, used in recipes/POs/stock) with **near-zero risk**
to inventory/sales/accounting — no changes to the stock core, no data migration of
stock. Ships quickly.
**Cons:** under the hood raw materials still share the `products`/`product_variants`
tables (distinguished by type). Purists may want literally separate tables — but
that distinction is invisible to users.

### Option B — Separate tables + polymorphic inventory *(the literal "separate tables")*

New `raw_materials` table; make `inventory_stock_levels`, `inventory_movements`,
`inventory_batches` **polymorphic** (`stockable_type` + `stockable_id` = variant OR
raw material); repoint `recipe_items`, `production_order_items`,
`stock_requisition_items`, `purchase_order_items`, `goods_receipt_items` to allow a
raw-material reference.

**Pros:** clean conceptual model; raw materials are truly their own entity.
**Cons:** **rewrites the inventory core** — every stock query, `PostInventoryMovementAction`,
`AdjustInventoryReservationAction`, costing, all the reports, and this session's
recipe/production/requisition/decimal work must be reworked for a polymorphic
stockable. Large surface, high regression risk, significant data migration.
Effort is comparable to Phases 0–3 combined.

### Option C — Separate tables + separate raw-material stock tables

`raw_materials` + parallel `raw_material_stock_levels` / `_movements` / `_batches`.
Avoids polymorphism but **duplicates** the entire inventory subsystem (two of
everything, two costing engines, two sets of reports). Highest long-term maintenance
cost. Not recommended.

---

## 5. Recommendation

**Option A.** It achieves every stated goal — a separate raw-materials catalog,
separate management, never sold, categorized, fully stockable and recipe-usable —
while treating "separate database tables" as the implementation detail it is, and
**not** destabilizing the inventory core we just built and tested (171 passing tests).
If a true table-level split is later required for reporting or licensing reasons, it
can be layered on; Option A does not block it.

The rest of this spec details **Option A**. Appendix B lists what Option B/C would
additionally entail if you choose the literal split.

---

## 6. Option A — Design detail

### 6.1 Data model
- `ProductType::RawMaterial = 'raw_material'` (new enum case + label).
- No new stock tables. Raw materials are `products` rows with
  `product_type = raw_material` and one variant each (reuse `syncDefaultVariant`).
- They keep `unit_category_id`, `track_inventory` (always true — they're stocked),
  and cost fields. `stock_policy` is irrelevant (not sold) — treat as tracked.
- A raw material has **no** selling price / storefront fields surfaced.

### 6.2 Management UI
- New **Raw Materials** nav item (gated by `inventory` or `fnb`), route group
  `admin.raw-materials.*` → a controller reusing catalog save logic scoped to the
  raw-material type:
  - list (with on-hand stock per location, reusing inventory reads),
  - create/edit (name, code/SKU, measurement category, cost, vendor, reorder),
  - quick stock-in / view movements (reuse existing inventory actions).
- The **Products & Services** list filters to `Product`/`Service`/`Bundle`
  (excludes raw materials) — a one-line query scope.

### 6.3 Recipes & production
- Ingredient pickers (recipe dialog, requisition dialog) scope to **raw materials +
  produced goods**, excluding plain sellable products where appropriate.
- Everything else (consume on production/sale, costing) already works via variants.

### 6.4 Selling guard
- `CreateSalesOrderAction` / storefront reject a line whose variant's product is a
  `raw_material` (defense-in-depth; the UI already won't list them).

### 6.5 Accounting / RBAC
- No accounting change — raw-material stock uses the existing inventory GL (1200).
- New permissions `raw-materials.view` / `raw-materials.manage` (or reuse
  `catalog.*` / `inventory.*`); add to `RoutePermissionMap` + role templates.

### 6.6 Migration
- Add the enum case (no schema change to `products` — `product_type` is a string).
- Optional reviewed backfill: reclassify products that are used only as recipe
  components and never sold → `raw_material`. Reversible; dry-run first.

### 6.7 Phasing
1. Enum case + selling guard + exclude from sellable lists (safe, invisible).
2. Raw Materials catalog UI (list/create/edit) + nav + permissions.
3. Scope recipe/requisition ingredient pickers to raw materials.
4. Optional reclassify-existing-ingredients data step.

### 6.8 Risks & tests
- Low risk — no stock-core change. Tests: a raw material can be created, stocked,
  received, put in a recipe, and **cannot** be sold or appear on storefront/POS;
  sellable lists exclude it.

---

## Appendix B — What Option B (literal separate tables) additionally requires

- `raw_materials` table (+ optional variants) and a **polymorphic stockable** on
  `inventory_stock_levels`/`_movements`/`_batches` (`stockable_type`,`stockable_id`),
  with unique keys and indexes reworked.
- Rewrite `PostInventoryMovementAction`, `AdjustInventoryReservationAction`,
  weighted-average costing, and every stock read/report to resolve a stockable.
- Repoint `recipe_items`, `production_order_items`, `stock_requisition_items`,
  `purchase_order_items`, `goods_receipt_items` to a raw-material reference (nullable
  polymorphic or dual columns) and update their actions/validation.
- Data migration of existing ingredient stock from variant-keyed to stockable-keyed.
- Full re-run and likely rewrite of the Phase 0–3 test suite.
- Estimated effort: comparable to Phases 0–3 combined; high regression risk.

## Open decisions

1. **Option A vs B/C** — recommend A. Confirm before building.
2. **Gating** — put Raw Materials behind `fnb`, or make it a general inventory
   feature (`inventory` module)? (Recommend `inventory`, since non-F&B businesses
   also buy raw inputs.)
3. **Permissions** — dedicated `raw-materials.*` or reuse `catalog.*`/`inventory.*`?
4. **Reclassify existing ingredients** now, or start clean and let tenants re-create?
