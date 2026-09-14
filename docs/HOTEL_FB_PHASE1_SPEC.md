# Phase 1 — Recipes & Production (production-first)

**Parent:** `HOTEL_FB_IMPLEMENTATION_PLAN.md`
**Date:** 2026-09-05
**Status:** Spec for review — no code until approved.

**Resequencing decision (2026-09-05):** the operator batch-cooks (1 bag rice +
0.5 kg oil + 10 Maggi → 10 cups jollof), so raw materials must deplete **at
production time**, and the finished good is held as stock and sold from it. This is
the production / finished-goods model. It swaps the original Phase 1 (sale-time
depletion) and Phase 2 (production): **production is built first**; sale-time
ingredient depletion becomes a later, optional add-on for pure cook-to-order items.

Everything here is gated behind the `fnb` module and reuses Phase 0 (units +
`UnitConverter`, decimal quantities, locations + `LocationResolver`, weighted-average
costing).

---

## STATUS — ✅ BUILT (2026-09-06)

Delivered and verified (full suite 163/163 real tests; 4 unrelated pre-existing reds):
- Movement types `production_out` / `production_in`; `production` accepted as a
  movement source (net-zero, no journal).
- Tables + models: `recipes`, `recipe_items`, `production_orders`,
  `production_order_items`.
- `RecordProductionAction` — atomic consume-raws → create-finished-good at
  `total input cost ÷ actual yield`; reuses decimals, UoM conversion, locations,
  weighted-average costing.
- `ProductionController` + `RecipeRequest`/`ProductionRequest`; routes
  `admin.inventory.production.*` (index/recipes.store/update/destroy/record);
  `RoutePermissionMap` entries (view→`inventory.view`, mutate→`inventory.manage`).
- Views: `production/index` + `recipe-dialog` (dynamic ingredient rows) +
  per-recipe `produce-dialog` (editable actual inputs + yield); `fnb`-gated
  "Production & Recipes" nav item.
- `ProductionTest` feature test (recipe create + record via HTTP, asserts
  depletion 100→80 / 50→45, finished 0→10, unit cost ₦4.50).

Reports delivered as: recent-production table + live batch-cost badge on each
recipe. Dedicated yield/wastage-variance and menu-margin report views are deferred
(the data — planned vs actual per line — is captured and ready for them).

## 0. The mechanic in one picture

```
Record production (a batch)
   consume raw materials  ── stock_out ──►  raw stock ↓ (rice, oil, Maggi)
   create finished good   ── stock_in  ──►  finished stock ↑ (10 cups jollof)
        finished-good unit cost = total input cost ÷ actual yield

Later, a sale of "Jollof (cup)"  ── stock_out ──►  finished stock ↓ by 1
        COGS recognised here, exactly as a normal tracked product today
```

Two separate stock events: **production** (raws → finished good) and **sale**
(finished good → customer). COGS happens at the sale, not at production —
production is a value-preserving transformation inside inventory.

---

## 1. Data model

### 1.1 Recipes (shared foundation)

**`recipes`**
- `id`, `tenant_id`
- `output_product_variant_id` — the finished good this recipe produces
- `name`
- `yield_quantity` `decimal(15,4)`, `yield_unit_id` (FK units_of_measure) — "makes 10 cups"
- `version` (int), `is_active` (bool)
- `prep_station_id` (nullable) — the arm that makes it
- `status`, timestamps
- index (`tenant_id`, `output_product_variant_id`, `is_active`)

**`recipe_items`** (the inputs)
- `id`, `tenant_id`, `recipe_id`
- `component_product_variant_id` — a raw material (or another produced item → nested)
- `quantity` `decimal(15,4)`, `unit_id` (FK units_of_measure)
- `wastage_percent` `decimal(6,3)` default 0
- `sort_order`, timestamps

A finished good is an ordinary product/variant; it becomes "producible" simply by
having an active recipe. Raw materials are ordinary tracked products.

### 1.2 Production orders

**`production_orders`**
- `id`, `tenant_id`
- `recipe_id`, `recipe_version` (snapshot)
- `output_product_variant_id`
- `source_location_id` — where raw materials are drawn from
- `output_location_id` — where finished goods are placed (defaults to source)
- `planned_quantity` `decimal(15,4)` — target yield
- `actual_yield_quantity` `decimal(15,4)` — what was really produced
- `total_cost_minor` (int) — sum of actual input costs
- `unit_cost_minor` (int) — total_cost ÷ actual yield
- `status` — draft | in_progress | completed | cancelled
- `produced_at`, `notes`, timestamps

**`production_order_items`** (input snapshot)
- `id`, `tenant_id`, `production_order_id`
- `component_product_variant_id`
- `planned_quantity` (recipe qty scaled to the batch), `actual_quantity` (editable)
- `unit_id`, `unit_cost_minor`, `line_cost_minor`
- timestamps

### 1.3 `stock_policy` — deferred

Not required for production-first. A finished good sells exactly like today's
`tracked` product (it holds its own stock, replenished by production instead of
purchase). The four-way `stock_policy` enum is introduced later, when sale-time
ingredient depletion (Model A) is added. Phase 1 detects "producible" via the
presence of an active recipe.

---

## 2. Workflows

### 2.1 Build a recipe
Under a new **Production** area (fnb-gated): pick the output product, set the yield
("makes 10 cups"), add ingredient lines (component + quantity + unit, e.g. 1 bag
rice, 0.5 kg oil, 10 ea Maggi). Live-costed as you go from each ingredient's current
average cost.

### 2.2 Record production (the core action)
Pick a recipe, choose a batch size (multiplier or target yield), and the source /
output locations (default to the kitchen store). The screen pre-fills planned input
quantities from the recipe scaled to the batch. On confirm, the user enters the
**actual** inputs used and the **actual** yield, then completes.

**On completion — one atomic transaction:**
1. For each input line: post a `stock_out` at `source_location_id` for
   `actual_quantity` (converted to the component's base unit via `UnitConverter`),
   through `PostInventoryMovementAction::executeFromSource` with a new `production`
   source type and `accountingHandledBySource = true` (so it does not post the normal
   adjustment journal). Sum the movement values = `total_cost_minor`.
2. Post a `stock_in` of the finished good at `output_location_id` for
   `actual_yield_quantity`, with `unit_cost_minor = total_cost_minor ÷ yield` — this
   updates the finished good's weighted-average cost correctly.
3. Snapshot `production_order_items` and stamp the order `completed`.

### 2.3 Selling the finished good
No new code — the finished good is a tracked product, so the existing POS / storefront
sale deducts its finished-goods stock and recognises COGS at its production-derived
average cost.

---

## 3. Accounting

Production is a **value-preserving transformation within inventory**, so with the
current single inventory control account (1200) the raw `stock_out` (−cost) and the
finished `stock_in` (+same cost) net to zero — no journal entry is needed, and none
is posted (both movements run with `accountingHandledBySource = true`). Inventory
value is conserved and simply reclassified from raws to the finished good.

- **No COGS at production** — COGS is recognised at the sale of the finished good, via
  the existing sale flow.
- **Yield effect on cost is automatic** — planned 10 cups but got 9? The same input
  cost spread over 9 raises the unit cost; nothing special to post.
- **Optional later:** split inventory into Raw Materials / WIP / Finished Goods
  sub-accounts and post a reclassification entry per production; and a wastage
  expense for spoiled inputs. Deferred — not needed for a correct MVP.

---

## 4. Reuse of Phase 0

- **UoM + `UnitConverter`** — recipe and production quantities in kg/L/cups/bags,
  converted to each component's base unit for depletion.
- **Decimal quantities** — 0.5 kg oil, 9.5 cups yield.
- **Locations + `LocationResolver`** — source (raws) and output (finished) stores;
  a kitchen arm produces into its own store.
- **Weighted-average costing** — finished-good cost from the actual batch; ingredient
  costs are live.
- **`fnb` module gate** — the entire Production area is hidden unless F&B is enabled.

## 5. Reports

- **Production history** — batches, inputs consumed, yield, unit cost.
- **Yield / wastage** — planned vs actual yield per recipe over time.
- **Recipe cost & finished-good margin** — live recipe cost vs the finished good's
  sell price.

## 6. Out of scope (later phases)

- **Sale-time ingredient depletion (Model A)** for pure cook-to-order items — the
  optional add-on that consumes the same recipe at the moment of sale.
- **Modifiers** ("extra meat") changing consumption.
- **Requisitions, stock-take, FEFO** — Phases 3–5.
- **RM/WIP/FG sub-accounts and wastage GL** — optional accounting refinement.

## 7. Settled build decisions (2026-09-05)

1. **Locations:** support both `source_location_id` and `output_location_id`, with
   `output_location_id` defaulting to the source — single-kitchen tenants never see
   the distinction.
2. **Menu:** Production is its own `fnb`-gated top-level menu item (not a tab under
   Inventory).
3. **Actual inputs/yield:** the completion screen pre-fills planned quantities from
   the recipe but lets the user edit the **actual** inputs used and the **actual**
   yield — the source of real yield/wastage/variance.
