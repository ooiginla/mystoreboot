# Phase 6 — Restaurant POS

**Status:** 6a, 6b, 6c, 6d and 6e built (2026-09-11). 6f (floor reporting & polish) outstanding.
**Depends on:** Phase 0 (locations, prep stations), Phase 1 (recipes/production), Phase 2 (sale-time depletion)
**Companion to:** `HOTEL_FB_IMPLEMENTATION_PLAN.md`

---

## 1. Why the retail till cannot do this

The existing POS (`retail-pos.blade.php`) is a **quick-sale till**: ring items, take money,
done. `CreateSalesOrderAction` and `CompleteSalesOrderAction` run back to back in one
request. That is correct for a supermarket and wrong for a restaurant in five ways.

| | Supermarket sale | Restaurant check |
|---|---|---|
| Lifetime | Seconds — created and completed in one action | Minutes to hours, open the whole time |
| Growth | Fixed at ring-up | Grows in rounds; guests keep ordering |
| Who holds it | Nobody — it is paid immediately | A **table**, and a server responsible for it |
| Kitchen | None | Each round **fires** to one or more stations |
| Ends by | Payment | Payment, but only after service charge, splits, and voids |

The object a restaurant needs is a **check** (bill/tab) — a long-lived order attached to a
table, fired to the kitchen in rounds, settled once at the end.

### The decision that follows

**A check is a `sales_order`, not a new parallel object.** Everything after "guest asks
for the bill" — payments, part-payments, change, receipts, till sessions, COGS, GL
posting, returns, refunds, wallet settlement — already exists on `sales_orders` and is
tested. A separate `restaurant_checks` table that "converts to an order at settle" would
duplicate that entire stack and guarantee the two drift apart.

So Phase 6 **extends** `sales_orders` with the dine-in dimension and adds the objects that
genuinely do not exist: tables, tickets, modifiers.

---

## 2. Data model

### 2.1 Service areas and tables

```
service_areas       id, tenant_id, branch_id, sales_point_id, name, sort_order, status
                    -- "Main Restaurant", "Pool Bar", "Terrace", "Room Service"

restaurant_tables   id, tenant_id, service_area_id, name/number, seats,
                    status (available | occupied | reserved | dirty), sort_order
```

A table belongs to a **service area**; the area belongs to a **sales point**, which
already carries `branch_id` and `default_location_id` (Phase 0). That chain is what makes
stock deplete from the right store and what scopes a server to their own floor.

Room service is modelled as a service area whose "tables" are rooms — which is also the
seam Phase 7 (lodging) will post folio charges through.

### 2.2 The check — extending `sales_orders`

```
+ service_area_id            nullable FK   -- null = retail sale, unchanged behaviour
+ restaurant_table_id        nullable FK
+ cover_count                unsigned int  -- guests seated; drives cover-average reporting
+ opened_at                  timestamp
+ check_status               open | bill_printed | settled | voided
+ service_charge_minor       int
+ service_charge_rate        decimal(5,2)  -- snapshot; rates change
+ parent_sales_order_id      nullable FK   -- set on checks created by a split
+ server_user_id             nullable FK   -- who is responsible for the table
```

`service_area_id IS NULL` means "this is an ordinary retail order" — every existing code
path behaves exactly as it does today. Nothing about the supermarket till changes.

### 2.3 Order items gain a service dimension

```
+ course              unsigned tinyint   -- 1 starters, 2 mains, 3 desserts…
+ seat_number         nullable int       -- who ordered it, for split-by-seat
+ fired_at            nullable timestamp -- null = still on the pad, not yet sent
+ void_reason         nullable string
+ voided_at           nullable timestamp
+ voided_by_user_id   nullable FK
```

`fired_at` is the pivot of the whole design (see §4).

### 2.4 Kitchen tickets

```
kitchen_order_tickets       id, tenant_id, sales_order_id, prep_station_id,
                            ticket_number, course,
                            status (queued | preparing | ready | served | cancelled),
                            fired_at, started_at, ready_at, served_at, notes

kitchen_order_ticket_items  id, tenant_id, kitchen_order_ticket_id,
                            sales_order_item_id, quantity, status
```

**Routing keys off the prep station.** A prep station is an **inventory location flagged
`is_prep_station`** — collapsed from the old separate `prep_stations` table on 2026-09-09,
because a bar or a grill is one thing in the real world: a place that holds its own stock
and makes food from it. Products name one as `prep_location_id`. Firing groups items by
that location and writes one KOT per station — the grill gets the suya, the bar gets the
drinks, and neither sees the other's work.

The collapse also removed a whole class of bug: "where it was made" and "whose stock it
came from" are now the same row and cannot disagree. Each line resolves its own store
through `LocationResolver` (station → the check's fallback) when it is added, so two bars
holding the same wine each deplete their own shelf.

**Still open:** one product routes to exactly one station. The same wine ordered on the
Terrace versus in the Restaurant cannot yet go to two different bars — that needs a
per-service-area routing override (`service_area_id`, `from_location_id`,
`to_location_id`), which is the natural next increment.

### 2.5 Modifiers

```
modifier_groups     id, tenant_id, name, min_select, max_select, is_required, sort_order
                    -- "Protein", "Spice level", "Sides"

modifiers           id, tenant_id, modifier_group_id, name,
                    price_delta_minor,                     -- may be negative
                    component_product_variant_id nullable, -- what it adds to the recipe
                    component_quantity nullable,
                    sort_order

product_modifier_groups   product_id, modifier_group_id, sort_order   -- pivot

sales_order_item_modifiers  id, tenant_id, sales_order_item_id,
                            modifier_id nullable, name, price_delta_minor,
                            component_product_variant_id nullable, component_quantity
```

Two things a modifier can do, and both matter:

1. **Change the price.** "Extra chicken +₦1,500" adds to the line total.
2. **Change what the kitchen consumes.** "No onions" should stop deducting onions; "extra
   chicken" should deduct more chicken.

The order-item snapshot copies name, price, and component — a modifier renamed or repriced
next month must not rewrite last month's bill or last month's COGS.

---

## 3. Workflows

### 3.1 Opening and building a check

1. Server picks a table in their service area → check opens (`check_status = open`,
   `opened_at`, `cover_count`).
2. Items are added to a **course**, optionally against a **seat number**, with modifiers.
   Nothing has reached the kitchen yet; `fired_at` is null and the item can be changed or
   removed freely.
3. Server hits **Fire** → items in that round are grouped by prep station, KOTs are
   written, `fired_at` is stamped, **and stock is depleted** (§4).
4. Repeat for later rounds. The check stays `open` and keeps growing.

### 3.2 Kitchen (KDS)

Each prep station has a screen showing its queued tickets. Staff move a ticket
`queued → preparing → ready`; the floor marks it `served`. Ticket age drives colour (§6.2).

### 3.3 Settling

1. **Print bill** → `check_status = bill_printed`, service charge computed and frozen.
2. Take payment through the existing `RecordSalesPaymentAction` — part-payments, mixed
   methods, and change already work.
3. On full payment the order completes through `CompleteSalesOrderAction`, the table is
   released to `dirty`, and the check becomes `settled`.

### 3.3a Cancelling a check (built with 6a)

A table seated by mistake must not be stuck. **A check with nothing fired can be
cancelled outright** — by anyone with `pos.restaurant.operate`, from the check page or
straight from the floor card when nothing has been ordered at all. The check is marked
`voided`, the reason is appended to its notes, and the table returns to *available* rather
than *needs cleaning*, because nobody was served.

Once a round has fired this route closes: the food was cooked, and for fire-timing tenants
the ingredients are already gone. Discarding that quietly would hide real waste, so a
fired check needs the reasoned, approved void in 6e. Until 6e ships, a fired check has no
way to close — that is deliberate, not an oversight, but it is the strongest argument for
doing 6d and 6e next.

### 3.4 Split and merge

- **Split by item / by seat** — move selected items to a new check carrying
  `parent_sales_order_id`. Fired items keep their KOT links; the kitchen is not disturbed
  by an accounting decision made on the floor.
- **Split evenly N ways** — *not* N checks. One check, N payment records. Splitting the
  object would fragment COGS and the audit trail for what is purely a payment convenience.
- **Merge** — move all items from check B into A, then void B with reason `merged`.

Service charge is always recomputed after a split or merge.

### 3.5 Void

The rule follows the food, not the paperwork:

| When | Stock effect | Control |
|---|---|---|
| Before fire | None — nothing was cooked | Server can remove it |
| After fire | Ingredients are **already gone**. The item becomes **waste** | Reason required + **manager approval** |

A post-fire void must never silently un-deplete stock. The food was cooked; someone ate
the cost. It is written off with a reason code so waste is measurable.

Approval reuses the existing engine: `ApprovalService::shouldDivert()` with type
`sales.void` and permission `sales.void.approve`, matching how inventory adjustments
already work. Tenants with approvals switched off void directly.

---

## 4. When stock is depleted — a per-tenant setting, defaulting to **fire**

This is the most consequential decision in the phase, and it differs from Phase 2.

**Decided:** both timings are supported, chosen per tenant, defaulting to fire.
`Modules\Sales\Support\DineInSettings::depletionTiming()` reads `dine_in.depletion` from
the tenant's existing `settings` JSON — `fire` (default) or `settle`. The reasoning below
is why fire is the default, not why it is the only option; a business that reconciles
strictly against paid sales can choose `settle` and keep the counter behaviour.

Phase 2 depletes a recipe when the **sale completes**, which is right for a counter where
ordering and paying are the same moment. In a restaurant they are hours apart, and the
ingredients leave the store when the **kitchen cooks**, not when the guest pays.

Depleting at fire means:

- Stock on hand is true **during** service, not only after everyone has paid. A manager
  checking whether there is enough chicken at 8pm gets a real answer.
- A walked table (never paid) still shows the food as consumed, because it was.
- A post-fire void is naturally waste rather than a reversal.
- Theoretical usage stays comparable with stock-take variance (Phase 4) within the shift.

Settling therefore does not deplete again for a fire-timing tenant. **Built with 6d:**
firing stamps each line with `ingredients_depleted_at` and the `consumed_cost_minor` it
used; `CompleteSalesOrderAction` skips depletion for any line so stamped and books COGS
from that recorded cost instead. The guard is on the line, not on the order's timing
setting, so changing the setting mid-service cannot cause a double deduction. A retail
order (never fired) keeps its old behaviour exactly. Voided lines are skipped at settle —
their cost was already written off as waste when they were voided.

---

## 5. Permissions and location scope

New permissions in `PermissionCatalogue`:

| Slug | For |
|---|---|
| `pos.restaurant.operate` | Open checks, add items, fire |
| `pos.restaurant.tables.manage` | Create/edit service areas and tables |
| `kds.view` | See a kitchen display |
| `kds.operate` | Move tickets through their statuses |
| `sales.void` | Request a void |
| `sales.void.approve` | Approve a post-fire void (manager) |
| `sales.service-charge.manage` | Set the rate |

**Scope** rides the chain already in place: user → branch → sales point → service area.
A server sees only checks in service areas of their active branch; a KDS screen is bound
to one prep station and shows nothing else. The existing `EnforcePermissions` middleware
and `RoutePermissionMap` cover the routes; the branch filter comes from
`ActiveBranchManager`, which the admin layout already resolves.

---

## 6. UI direction

Two audiences with opposite needs. They must not share a layout.

### 6.1 Floor (server) — fast and dense

- **Table map first**, not a product grid. Colour-coded by state: available, occupied,
  bill printed, needs cleaning. Each occupied table shows cover count, elapsed time, and
  running total — a manager reads the room in one glance.
- Opening a table goes straight to the check: items on the left, category/product grid on
  the right, big **Fire** button that is visibly *disabled until there is something
  unfired*, showing the count ("Fire 4 items").
- Unfired items must look **visually different** from fired ones — dashed border, "not
  sent" chip. The single worst POS bug is a server thinking food is on when it is not.
- Modifiers open as a sheet with large tap targets; required groups block until chosen.

### 6.2 Kitchen (KDS) — readable across the room

This is a wall screen seen from two metres by someone holding a pan. It gets its own
full-bleed layout with **no admin chrome, no sidebar**.

- **Dark ground, bright cards.** High contrast survives kitchen glare and steam far better
  than a white page.
- **Station name huge** in the header, plus a live clock and queue count. Anyone walking in
  knows which screen they are looking at.
- Tickets as a column-flow board of large cards: table, course, cover count, item lines at
  large weight, modifiers directly under their item in a **contrasting colour** — a missed
  "no peanuts" is a safety incident, not a service slip.
- **Age drives colour, and it must be unmissable**: fresh (calm green), > 8 min (amber), >
  15 min (red, with the card pulsing). Thresholds per tenant.
- Colour is never the only signal — every state carries a label and an icon, for colour
  vision deficiency and for glare.
- Bump controls are full-width buttons sized for a gloved thumb: **Start → Ready**.
- Auto-refresh by polling (5s). No websocket infrastructure exists yet and a kitchen
  screen tolerates five seconds; this is deliberately not over-built.

### 6.3 Non-negotiables

Beauty here is legibility under pressure. Concretely: nothing critical under 16px on the
floor or 28px on the KDS; every colour state paired with text; destructive actions
(void, merge) visually distinct and never adjacent to routine ones; and the fire button
never in the same place as a delete.

---

## 7. Accounting

- **Food and drink** — unchanged; existing revenue posting.
- **Service charge** — a separate revenue line, its own account, so it can be reported and
  (where distributed to staff) reconciled. Tenants configure whether it is taxable.
- **COGS** — booked at fire, not at settle (§4).
- **Post-fire void** — written off as waste to the existing shrinkage account, carrying its
  reason code, so it lands in the same place stock-take variance does.

---

## 8. Sub-phases

Ordered so each one is independently useful and shippable.

| Sub-phase | Scope | Depends on | Why it earns its place |
|---|---|---|---|
| **6a — Tables & open checks** | Service areas, tables, table map, check that opens and grows, cover count, server assignment | 0 | The bill that stays open — the core difference from retail. Usable alone as a tab system. |
| **6b — KOT & KDS** | Fire a round, route by prep station, ticket lifecycle, full-bleed kitchen screens, deplete stock at fire | 6a, 1, 2 | Connects floor to kitchen; makes stock true during service. |
| **6c — Modifiers** | Modifier groups, per-product attachment, price deltas, recipe component adjustments, KOT display | 6b | "No onions" and "extra meat" — both price and consumption. |
| **6d — Service charge & settle** | Rate config, computation, bill print, settle through existing payments, table release | 6a | Closes the loop; the check becomes money. |
| **6e — Split, merge & void** | Split by item/seat, split evenly as payments, merge, void with pre/post-fire rules and manager approval | 6d | The messy real-world end of service. |
| **6f — Floor reporting & polish** | Cover averages, table turn time, server performance, void/waste report, KDS thresholds per tenant | 6b, 6e | Turns the data already being captured into management information. |

**Suggested build order: 6a → 6b → 6d → 6c → 6e → 6f.** Settle (6d) before modifiers (6c)
so there is a complete, sellable loop — open, fire, cook, pay — before the model gets
richer.

---

## 9. Deliberately out of scope

- **Reservations and waitlist.** A booking system is its own domain and overlaps Phase 7.
- **Websocket/live push.** Polling is honest for the volume; revisit when there is a
  general realtime layer, not for one screen.
- **Table-side handheld ordering.** The floor UI will be built responsive, but a dedicated
  handheld app is a separate product decision.
- **Tip distribution / tronc.** Service charge is captured and reported; how it is shared
  among staff is a payroll problem.
- **Course firing timers ("fire mains 10 min after starters").** The `course` column is
  recorded from 6a so this can be added later without migration.
