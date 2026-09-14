# Storeboot → Hotel F&B: Ideal Implementation Plan

**Author:** Engineering review
**Date:** 2026-09-05
**Companion to:** `HOTEL_INVENTORY_USE_CASE_ANALYSIS.md`

This document does two things:

1. **Reviews the existing analysis** — validates its claims against the current
   codebase and calls out what it got wrong or missed.
2. **Proposes an ideal plan** — the data model, workflows, accounting, and phased
   delivery for Storeboot to support a hotel F&B + stores operation *well*, rather
   than bending the use case to fit what exists today.

The guiding principle (per the brief): **don't force the use case into the current
model — design what should be ideal, then show how Storeboot's existing seams make
it cheap to build.**

---

## Part A — What the analysis got right, wrong, and missed

### A.1 Confirmed accurate

The analysis is a strong, honest assessment. Verified against the code, these
claims hold:

- **Integer-only quantities.** Every physical quantity is `integer`:
  `inventory_stock_levels.quantity_on_hand` / `quantity_reserved`,
  `inventory_movements.quantity`, `inventory_batches.quantity_remaining`,
  reorder fields. No decimals anywhere. (`modules/Inventory/database/migrations/2026_06_06_000001_create_inventory_tables.php`)
- **No unit-of-measure model.** Nothing resembling UoM, pack size, or
  conversions exists in any module.
- **No recipe / BOM / production.** No models, actions, or tables.
- **Departments aren't inventory locations.** `Modules\Business\Models\Department`
  is an org-chart record with no branch or location link.
- **POS location is not per-till.** `retail-pos.blade.php` binds
  `inventory_location_id` to `$posLocations->first()?->id` — the first filtered
  location, not a configured default for the till/outlet.
- **Batches are captured but never depleted by rule.** No FEFO/FIFO depletion,
  no lot traceability on outbound movements.

### A.2 Needs correcting / sharper framing

- **`Bundle` is completely inert, not a partial foundation.** `ProductType::Bundle`
  exists as an enum label only — there is **no** component/child table and **no**
  explosion at sale. The analysis frames this as "no bundle-component *or recipe*
  relationship," which understates it: bundles do nothing today. Good news — that
  means we can design the composition model (recipes/BOM) cleanly without
  unwinding an existing half-implementation.
- **Batch-aware depletion is a smaller lift than implied.** `inventory_batches`
  already carries `batch_number`, `expiry_date`, `quantity_remaining`, and
  `stock_condition`. The table and inbound capture exist; what's missing is only
  (a) a `batch_id` on outbound movements and (b) a depletion strategy that picks
  and decrements a batch. This is a Phase-5 refinement, not a from-scratch build.

### A.3 What the analysis missed

These are the material gaps the analysis did **not** cover — and some are bigger
than anything on its list.

1. **It silently narrowed "hotel" to "F&B stores."** A hotel has two more core
   domains, both absent from the doc *and* the codebase:
   - **Restaurant table-service POS** — tables/covers, open tabs, kitchen order
     tickets (KOT) / kitchen display (KDS) routed by station, course firing,
     split/merge bills, service charge & tips, void/comp with reason and
     authorization. The current POS is a **retail quick-sale till**; it has no
     concept of a table or an open check.
   - **Lodging / rooms** — reservations, availability, guest folio, posting
     room-service charges to a room, night audit. Entirely out of scope of both
     the doc and the system.
   A plan for a "full hotel deployment" must at least *name* these so nobody
   mistakes "F&B inventory done" for "hotel-ready."

2. **The central design decision is never stated: how does a menu item deplete
   stock at the moment of sale?** There are two legitimate models, and real
   hotels use **both**:
   - **Recipe depletion at sale** (à la carte, cooked to order): sell "Jollof
     Rice" → automatically consume its recipe's ingredients. No finished-goods
     stock is held.
   - **Production-to-finished-goods** (bakery, bulk sauces, central kitchen):
     a production order consumes inputs and yields finished goods held as stock,
     later sold from that stock.
   The analysis lists "recipes" and "production orders" as two separate gaps but
   never connects either to the **sale event**, and never reconciles them with the
   **`track_inventory` (made-to-order) policy already shipped**. This is the most
   important architectural choice in the whole effort.

3. **No stock-take / physical count workflow.** The doc mentions counts only as a
   *decimal* limitation. The real gap is the **absence of a counting process**:
   blind counts, snapshot vs counted variance, cost variance, and posting
   adjustments under approval. Only `SalesTillClosingCount` exists (cash drawer).
   This is the #1 F&B inventory control and it isn't on the list.

4. **No theoretical-vs-actual usage variance.** The doc lists "food cost %" but
   the real control is **variance**: what the recipes say *should* have been used
   vs what the count says *was* used. That's the entire payoff of recipes and it's
   unmentioned.

5. **Purchase requisition → approval → PO (the front half of procurement).** The
   doc covers PO *receipt* but not the request/approval that precedes it — and
   Storeboot **already has an approvals engine** (per-business toggle, built into
   RBAC) that fits this perfectly. The doc never connects them.

6. **Waste/spoilage as a first-class, reason-coded transaction.** A `damaged`
   movement type exists, but there's no reason taxonomy, no waste log, no waste
   report, and no approval — core to F&B cost control.

7. **Modifiers.** "No onions," "double meat," combos — these change both **price**
   and **ingredient consumption**. Not mentioned, and central to any F&B POS.

8. **Par levels & suggested replenishment.** The doc mentions reorder level; par
   levels per outlet (with auto-suggested requisitions/POs to a source location)
   are the standard hotel replenishment mechanism and are absent.

### A.4 Seams already in place that make this cheap

The plan below deliberately reuses what shipped recently:

- **Per-product `track_inventory` policy** → generalize to a `stock_policy` enum.
- **RBAC approvals engine** (per-business toggle) → reuse for requisitions,
  stock-takes, and waste, instead of new approval plumbing.
- **Weighted-average `average_cost_minor`** → recipe cost is always live, no
  separate cost table.
- **Provider-agnostic GL / `EnsureDefaultChartOfAccountsAction`** → RM/WIP/FG and
  variance accounts slot into the existing chart-of-accounts pattern.
- **Provider-agnostic payment/payout seam + subscription module gating** → the
  restaurant POS and (later) lodging can be **billable add-on modules**.

---

## Part B — Ideal target model

### B.1 Units of measure & decimal quantities (the foundation)

Everything else depends on this, so it goes first.

- **`units_of_measure`** (tenant-scoped): `code`, `name`, `dimension`
  (count | weight | volume | length), `is_base_for_dimension`.
- **`unit_conversions`**: `from_unit_id`, `to_unit_id`, `factor` — plus
  per-product overrides for pack sizes ("1 carton = 24 bottles") where the
  conversion is product-specific rather than universal.
- **Product gains** `base_unit_id` (its stock-keeping unit), and each context
  declares its unit: purchase unit (PO), stock unit (base), recipe unit,
  sales unit. All are converted to the base unit for storage.
- **Decimal migration.** Convert every quantity column from `integer` to
  `decimal(15,4)`:
  `inventory_stock_levels.quantity_on_hand`/`quantity_reserved`/reorder fields,
  `inventory_movements.quantity`, `inventory_batches.quantity_remaining`, and the
  quantity fields on sales orders, POs, and returns.
  - **Risk:** this is the widest-blast-radius change in the project. Every place
    that does integer math, casts to `int`, or formats a quantity must be audited
    (`PostInventoryMovementAction`, `CreateSalesOrderAction`,
    `CompleteSalesOrderAction`, `ReceivePurchaseOrderAction`, reservation
    adjustments, all Blade `number_format` calls). Isolate in its own phase with a
    heavy test pass.

### B.2 Location model upgrades

- Tenant-configurable location **types** (not the current fixed enum).
- **Parent-child hierarchy** (`parent_id`) — e.g. Central Store → Kitchen Store →
  Line Fridge.
- `is_sellable_point` and per-till/outlet **`default_inventory_location_id`** so
  each POS depletes from the right place automatically.
- **`replenishment_source_location_id`** per outlet — the default source for
  requisitions and par-based suggestions.

### B.3 Recipes / Bill of Materials

- **`recipes`**: `output_variant_id` (the menu item / produced good),
  `yield_quantity`, `yield_unit_id`, `version`, `is_active`, `prep_station`
  (routes KOT later).
- **`recipe_items`**: `recipe_id`, `component_variant_id`, `quantity`, `unit_id`,
  `wastage_percent`, `is_optional`, `substitute_group`. Components may themselves
  be produced items → **nested sub-recipes**.
- **Cost** = Σ(component `average_cost_minor` × qty × (1 + wastage%)), computed
  live off existing weighted-average cost. Menu margin = sell price − recipe cost.
- **Versioning** freezes the recipe used by a given production order / sale for
  auditability.

### B.4 Menu-item stock policy (ties recipes to the sale)

Generalize the shipped `track_inventory` boolean into a per-product/variant enum
**`stock_policy`**:

- `tracked` — physical stock, deplete own on-hand (today's behaviour).
- `recipe` — hold no own stock; on sale, explode the active recipe and post a
  `stock_out` per ingredient at the sale location.
- `bom_finished` — sold from produced finished-goods stock (see B.5).
- `none` — pure service / made-to-order, no depletion (today's `track_inventory=false`).

`CompleteSalesOrderAction` / storefront completion branch on this. This is the
piece the analysis omitted and it makes à la carte "cook-to-order" work **without**
needing production orders.

### B.5 Production orders

- **`production_orders`**: `output_variant_id`, `recipe_id` + version snapshot,
  `planned_qty`, `actual_yield_qty`, `location_id`, `status`
  (draft | in_progress | completed | cancelled).
- On completion, **atomically**: consume inputs (`stock_out` per recipe line at
  source location), produce output (`stock_in` of finished good at actual yield &
  computed unit cost), record yield/wastage variance, post GL.
- Powers bakery / central-kitchen / batch-prep.

### B.6 Requisition, dispatch & receipt (departmental replenishment)

- **`stock_requisitions`** (+ line items): requesting location, source location,
  `status` (draft | submitted | approved | rejected | dispatched | received).
  **Reuse the RBAC approvals engine** for submit→approve.
- **Dispatch** → `transfer_out` into stock-in-transit; **Receipt** → `transfer_in`
  with received qty, allowing shortfall/variance capture. Multi-line document
  (today's transfer dialog is single-item).
- **Par levels** per outlet drive suggested requisition quantities.

### B.7 Stock-take sessions

- **`stock_counts`**: `location_id`, `status` (open | counting | review | posted),
  `is_blind`.
- **`stock_count_items`**: `variant_id`, `system_qty` snapshot, `counted_qty`,
  `variance_qty`, `variance_cost_minor`.
- Posting creates `adjustment_in`/`adjustment_out` movements + variance GL, under
  approval. Enables **theoretical-vs-actual usage variance** once recipes exist.

### B.8 FEFO / batch depletion & traceability

- Add `batch_id` to outbound movements; deplete by earliest `expiry_date` (FEFO),
  configurable to FIFO.
- Decrement `inventory_batches.quantity_remaining`; expiry & recall reports fall
  out for free. (Table already exists — small lift.)

### B.9 Restaurant table-service POS (separate F&B module)

The largest net-new UI; a billable add-on module:

- Tables/sections, covers, open tabs/checks.
- KOT/KDS routed by `recipe.prep_station`; course firing.
- Split/merge bills; service charge & tips; seat/waiter assignment.
- Void/comp with reason + authorization (RBAC).
- Modifiers that adjust price **and** ingredient consumption.

### B.10 Accounting

Extend `EnsureDefaultChartOfAccountsAction`:

- Raw Materials Inventory, Work-in-Progress, Finished Goods (or one Inventory
  control account with a product-class sub-ledger).
- Production/yield variance account; wastage expense account.
- Production completion, stock-take variance, and waste all post through these.

### B.11 Cross-cutting: location-scoped RBAC

Phases 6.6+ need it: extend `EnforcePermissions` so a role can be scoped to a
branch/location (a store clerk sees only their store's stock and requisitions).
Called out separately because it touches the whole authorization layer.

---

## Part C — Phased delivery

Each phase ships standalone value; later phases depend on earlier ones only where
noted. Retail (non-hotel) tenants benefit from Phases 0–4 too.

| Phase | Scope | Depends on | Payoff |
|------|-------|-----------|--------|
| **0 — Foundations** ✅ | Decimal quantities + UoM (now unit *categories*) + conversions; location hierarchy, configurable types, sellable points, replenishment source | — | Stock in kg, L, cartons; correct outlet depletion. **Built 2026-09-05.** |
| **1 — Recipes & production (production-first)** ✅ | Recipe/BOM + production orders: cook a batch → consume raw materials → finished goods enter stock at computed cost; yield/wastage; sales deduct finished goods | 0 | Matches batch-cooking reality (jollof in trays). **Built 2026-09-06.** See `HOTEL_FB_PHASE1_SPEC.md`. |
| **2 — Sale-time ingredient depletion** ✅ | `stock_policy` (StockPolicy enum), explode the recipe at the moment of sale; wired into POS-immediate + order completion; menu-margin/food-cost report | 0, 1 | À la carte cooked-to-order with no finished-goods stock. **Built 2026-09-06.** |
| **3 — Requisition & transfer workflow** ✅ (MVP) | Multi-item requisition, submit→approve→fulfil (atomic source→dest transfer); `fnb`-gated | 0 | Departmental replenishment. **Built 2026-09-06.** Dispatch/in-transit/receipt split + par levels deferred. |
| **4 — Stock-take & variance** ✅ | Count sessions (blind by default), per-line variance qty + cost, posting writes adjustment movements + GL; variance *is* the theoretical-vs-actual gap because recipes already deplete theoretical usage | 0, 1 | The core F&B cost control. **Built 2026-09-07.** Posting gated on `inventory.adjustments.approve`. |
| **5 — FEFO / batch depletion** ✅ | Outbound movements draw lots first-expired-first-out (undated lots last, then FIFO); `inventory_movement_batches` records each allocation; transfers carry lot identity to the destination | 0 | Expiry control, recalls. **Built 2026-09-07.** Batches previously only ever accumulated — nothing consumed them. |
| **5a — Lot trace & recall UI** ✅ | Searchable lot list (incl. used-up lots), per-lot trace of every draw, downstream chain across transfers via `source_inventory_batch_id` | 5 | Answers "where did this lot go?" for a recall. **Built 2026-09-08.** |
| **5b — Lot capture usability** ✅ | Production output auto-lotted (batch no. from the order, expiry from a new recipe `shelf_life_days`); movement dialog explains what batch/expiry unlock; guided empty state on Lot traceability | 5, 5a | Lot tracking previously only worked for goods you *buy*, not goods you *make*. **Built 2026-09-08.** |
| **3a — Requisition stock visibility & reservations** ✅ | Source availability shown per line as it is picked (warns, never blocks); approval reserves via `quantity_reserved` and releases on fulfil/cancel; cross-tenant id defect in `RequisitionRequest` fixed | 3 | An approval now actually promises the stock. **Built 2026-09-08.** |
| **6 — Restaurant POS** | Tables, KOT/KDS, tabs, splits, service charge, modifiers, void/comp; location-scoped RBAC | 1 | True table-service dining; billable add-on. **Spec written 2026-09-08** — see `HOTEL_FB_PHASE6_SPEC.md`. Broken into 6a–6f below. |
| &nbsp;&nbsp;**6a — Tables & open checks** ✅ | Service areas, tables, colour-coded floor map, a check that opens and keeps growing, cover count, server assignment; `restaurant` billable module | 0 | The bill that stays open — the core difference from a retail sale. **Built 2026-09-08.** A check is a `sales_order` with a service area; null area = retail, untouched. |
| &nbsp;&nbsp;**6b — KOT & KDS** ✅ | Fire a round, route by `prep_station_id`, ticket lifecycle, full-bleed dark kitchen screens with age-banded cards, **depletion timing per tenant (default: at fire)** | 6a, 1, 2 | Connects floor to kitchen; makes stock true during service, not after. **Built 2026-09-08.** |
| &nbsp;&nbsp;**6b1 — Prep stations = locations** ✅ | `prep_stations` folded into `inventory_locations.is_prep_station`; products/recipes/tickets point at the location; firing resolves each line's store via `LocationResolver` | 6b | One row for a bar means "where it was made" and "whose stock it came from" cannot disagree. **Built 2026-09-09.** Two bars now keep separate stock correctly; same-item-two-bars *routing* still pending. |
| &nbsp;&nbsp;**6c — Modifiers** ✅ | Modifier groups (required, min/max), options with signed price and ingredient deltas, per-product attachment, choice sheet on the pad, snapshot on the line, loud KDS display | 6b | "No onions" / "extra meat" — changes both price and consumption. **Built 2026-09-11.** Same-choice lines merge; different choices stay separate. |
| &nbsp;&nbsp;**6d — Service charge & settle** ✅ | Rate snapshot at open, waive per bill, restaurant settings dialog, bill print (blocked while items are unsent or a void is pending), printable bill, settle via `RecordSalesPaymentAction` + till, part/even-split payments, cash change, table → needs cleaning; service charge to its own revenue account **4050** | 6a | Closes the loop; the check becomes money. **Built 2026-09-11.** The double-depletion guard is in (per-line `ingredients_depleted_at`). |
| &nbsp;&nbsp;**6e — Split, merge & void** ✅ | Split by item or part-quantity into sibling checks on the same table, merge (tickets follow the food), post-fire void of a line or part of one, void whole check; waste → EXP-6050; `sales_void` approval type with `sales.void` / `sales.void.approve` | 6d | The messy real end of service; post-fire void is waste, not a reversal. **Built 2026-09-11.** Split-evenly is N payments on one check, as specified. |
| &nbsp;&nbsp;**6f — Floor reporting & polish** | Cover averages, table turn time, server performance, void/waste report, per-tenant KDS thresholds | 6b, 6e | Turns captured data into management information. |
| **7 — Lodging (separate track)** | Reservations, folio, room-service posting, night audit | — | Full "hotel," not just F&B. Explicitly out of the inventory scope. |

Deferred items (expiry alerting, write-off by lot, FEFO/FIFO toggle, and smaller carry-overs) are tracked in `HOTEL_FB_DEFERRED_WORK.md`.

**Recommended first build:** **Phase 0 then Phase 1.** Phase 0 is pure enabling
plumbing (and the riskiest, so it gets isolated and tested hard); Phase 1 turns it
into a visible hotel win — a kitchen can sell a plated dish and watch its raw
ingredients deplete at cost — while building directly on the made-to-order work
already shipped.

---

## Part D — Decisions

**Settled (2026-09-05):**

1. **Scope of "hotel": F&B + stores now (Phases 0–6).** Lodging (Phase 7) is a
   separate, later, undecided track and must **not** shape the Phase 0 data model
   (locations, accounting) — we do not pre-model rooms/folios.
2. **Decimal strategy: `decimal(15,4)` columns.** Quantities are true decimals,
   rounded to 4 dp at each movement; money always rounds to the currency minor
   unit at the cost boundary. This rounding discipline is a hard rule for Phase 0.

**Still open (not blocking Phase 0/1):**

3. **Recipe depletion vs production:** both models are assumed wanted, so
   `stock_policy` is designed for all four values (`tracked`, `recipe`,
   `bom_finished`, `none`) up front. Confirm before Phase 1.
4. **Billing:** should the Restaurant POS be a separate paid module under the
   existing subscription gating, or bundled into Enterprise? (Decide before Phase 6.)
5. **Location-scoped RBAC:** needed from Phase 3, or defer until Phase 6?
