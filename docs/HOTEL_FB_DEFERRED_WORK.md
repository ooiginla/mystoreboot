# Deferred work — F&B / advanced inventory

Backlog of work consciously left undone. Each item says what exists today, what is
missing, why it was deferred, and roughly what building it involves. Nothing here is a
bug: these are scope decisions, and the system is coherent without them.

Companion to `HOTEL_FB_IMPLEMENTATION_PLAN.md` (the phase table) — that document tracks
what shipped, this one tracks what did not.

Last reviewed: 2026-09-08, after Phases 4 (stock-take), 5 (FEFO), 5a (lot trace UI),
5b (lot capture usability) and item 6 (source-stock visibility + reservations) shipped.

> Resolved since first draft: **production output is no longer unlotted.** Every production
> run now creates a lot named after the order, with an expiry date when the recipe declares
> a `shelf_life_days`. That was the largest hole in lot coverage — traceability previously
> only worked for goods a tenant *bought*, never for goods they *made*.

---

## 1. Expiry alerting

**What exists.** Inventory → *Expiry / condition* tab lists lots expiring within 30 days,
and the *Lot traceability* page has an "Expiring within 30 days" filter. The lot trace
view flags a lot that is past its expiry date but still holding stock.

**What is missing.** Everything is pull-only — somebody has to go and look. There is no
push: no email or in-app notification, no dashboard badge, no daily digest, no
per-tenant threshold (30 days is hardcoded in two places), and no way to mark an alert
as acknowledged so it stops appearing.

**Why deferred.** Alerting is a cross-cutting concern, not an inventory one. Storeboot
has no general notification/digest infrastructure yet, and building a bespoke mailer for
expiry alone would be the wrong shape — the next feature that needs alerts (low stock,
approvals, failed payouts) would either duplicate it or have to rip it out.

**Rough shape when picked up.**
- A tenant setting for the warning window (default 30 days), replacing the two hardcoded
  `addDays(30)` calls in `InventoryController::index()` and `BatchTraceController::index()`.
- A scheduled command scanning `inventory_batches` for lots inside the window with
  `quantity_remaining > 0`, grouped per tenant and location.
- Delivery through whatever notification layer exists at that point. Deliberately not
  specified here — the point of deferring is to let that decision be made once, globally.
- An acknowledgement flag, otherwise a lot that is legitimately being run down will nag
  every single day until it is gone.

**Dependency.** A general notification/digest mechanism. Do not build this standalone.

---

## 2. Write-off by lot

**What exists.** Stock can be written off through the *Post movement* dialog
(`stock_out`, `damaged`) against a variant + location. When that posts, FEFO decides
which lots are consumed — earliest expiry first.

**What is missing.** No way to say *"write off this specific lot"*. The realistic case is
the one FEFO gets wrong on purpose: a mid-life lot spoils because a fridge failed, while
an earlier-expiring lot is perfectly fine. Today the write-off silently consumes the
earlier lot and leaves the spoiled stock on the books, which corrupts both the batch
records and the recall trail.

**Why deferred.** FEFO is right for the overwhelming majority of outbound movements, and
the failure mode above needs a genuinely different UI (pick the lot, not the item). It
was cleaner to ship automatic FEFO first and add manual override as its own increment
than to complicate every movement form with a lot picker nobody usually wants.

**Rough shape when picked up.**
- A "Write off this lot" action on the lot trace page (`admin/batches/show.blade.php`),
  pre-scoped to that batch, asking for quantity + reason.
- `DepleteBatchesAction` needs an explicit-batch path. Today `execute()` always allocates
  FEFO across eligible lots; it needs a sibling that draws from one named batch, still
  writing an `inventory_movement_batches` row so the trail holds.
- Guard: the requested quantity cannot exceed that lot's `quantity_remaining`, which is a
  tighter constraint than the location-level check `assertEnoughStock()` performs.
- Consider whether this needs its own permission, or whether `inventory.adjust` covers it.
  Leaning toward reusing `inventory.adjust` — it is the same class of action.

**Watch out.** Non-sellable lots are excluded from the FEFO pool
(`DepleteBatchesAction::availableBatches()`). A manual write-off of an already-damaged
lot must bypass that filter or it will silently allocate nothing.

---

## 3. FEFO / FIFO strategy toggle

**What exists.** Depletion order is fixed: earliest `expiry_date` first, undated lots
last, ties broken by `id` ascending. A tenant that never records expiry dates therefore
gets plain FIFO with no configuration at all.

**What is missing.** No setting to force FIFO, LIFO, or manual selection on tenants that
do record expiry dates.

**Why deferred — and why this may never be worth building.** The automatic fallback
already covers the realistic cases. FEFO is correct for anything perishable; FIFO is what
you get for free on anything that is not. LIFO is a costing convention, not a physical
picking rule, and applying it to physical stock movement would be actively wrong for food
businesses. This is listed for completeness because the original plan named it, not
because there is demand.

**Rough shape if picked up.** A `depletion_strategy` column on the tenant or on
`inventory_locations`, read by `DepleteBatchesAction::availableBatches()` to swap the
`orderByRaw` clause. Perhaps two hours of work — the reason to hold off is product
judgement, not effort.

**Recommendation.** Leave it. Revisit only if a real tenant asks, and when they do, find
out what they actually mean — it is usually a costing question (which is weighted-average
in Storeboot and unrelated to picking order) rather than a picking one.

---

## 4. Stock in transit (dispatch → receipt split)

**What exists.** Both transfer paths — *Transfer stock* on Inventory, and requisition
fulfilment — move stock out of the source and into the destination in **one atomic step**
(`PostInventoryMovementAction::postTransfer`). The moment you click fulfil, the stock is
counted as present at the destination.

**What is missing.** There is no state for stock that has left but not arrived. Today the
van driver's load is, as far as the system is concerned, already on the destination's
shelf. That means:

- The destination can sell or consume stock that is physically still on a van.
- A short or damaged delivery has nowhere to be recorded — the discrepancy only surfaces
  at the next stock take, as unexplained shrinkage at the destination that actually
  happened in transit.
- Nobody can answer "what is on the road right now?"

**Why deferred.** Atomic transfer is correct within one building, which is the common
case (central store → grill kitchen, a few metres apart). The split only earns its
complexity when source and destination are genuinely separate sites with real travel time
between them. Shipping it early would have added a receipt step to every kitchen-to-
kitchen move for no benefit.

**Rough shape when picked up.**
- A holding location per transfer, or an `in_transit` state on the movement pair. Leaning
  toward a real **in-transit location** owned by the source, so stock in transit still has
  a location and still appears in valuation — losing it from the balance sheet mid-journey
  would be wrong.
- Split the single transfer into **dispatch** (source → in-transit) and **receipt**
  (in-transit → destination), with the receipt recording quantity actually received.
- A variance path for the difference between dispatched and received: short, damaged, or
  over. This is the whole point of the feature, so it should not be an afterthought — it
  needs a reason code and should post like a write-off, at the *source's* cost.
- Requisition gains a `dispatched` status between `approved` and `fulfilled`.
- Lot identity must survive both hops. `DepleteBatchesAction::mirrorToDestination()`
  currently mirrors source lots straight to the destination; it would need to mirror to
  the in-transit location on dispatch and again on receipt, keeping
  `source_inventory_batch_id` chained at each hop so a recall still follows the trail.
- A "Goods in transit" view, and a guard so the destination cannot draw on stock that has
  not been received.

**Keep the direct path.** Even after this ships, *Transfer stock* should stay atomic for
same-site moves. Forcing a two-step receipt on a move between two rooms would be worse,
not better. Make the split a property of the route (or opt-in per transfer), not a global
rule.

---

## 5. Partial fulfilment closes a requisition

**What exists.** The fulfiller can enter a fulfilled quantity per line lower than the
requested one, and the shortfall is visible on the requisition afterwards.

**What is missing.** Fulfilling short still marks the whole requisition `fulfilled`
(`FulfilStockRequisitionAction` sets the status unconditionally after the loop). The
outstanding balance is not tracked, cannot be shipped later, and appears nowhere as
"still owed". The requesting store has to notice and raise a fresh requisition.

**Why deferred.** Recording the shortfall was enough to be honest about what happened;
tracking the outstanding balance is a genuinely bigger workflow (back-orders).

**Rough shape when picked up.** A `partially_fulfilled` status that keeps the document
open, repeated fulfilment against the same requisition, and a rule for when the requester
closes it off as short-shipped rather than waiting. Pairs naturally with item 4 — both
change the same status machine, so do them together rather than twice.

---

## 6. Requesting is blind to source stock — ✅ CLOSED 2026-09-08

**Shipped.** Both halves of this item are done.

- **Visible availability.** The requisition dialog now shows *"Source has 12 kg available"*
  under each line, recalculated as the source store, the item, or the quantity changes.
  Asking for more than exists turns the note red and says so. It **warns without
  blocking** — a deliberate shortfall is a legitimate request, and the fulfiller decides.
- **Reserve on approval.** `ReserveRequisitionStockAction` holds the requested quantity in
  `inventory_stock_levels.quantity_reserved` on approve, and releases it on fulfil or
  cancel. Approving now genuinely promises the stock: a second requisition against the
  same balance is refused at approval time with a clear message, instead of failing later
  at fulfilment.

**Two ordering rules worth remembering if this is touched again.**

1. `FulfilStockRequisitionAction` **releases the hold before posting the transfer.** The
   requisition's own reservation would otherwise make its own stock look unavailable to
   `assertEnoughStock()` and the fulfilment would fail against a hold it placed itself.
2. Reservation is **all-or-nothing per requisition**. A partial hold would leave stock
   locked away with nothing to release it, so a line that cannot be covered aborts the
   whole approval and leaves the status at `submitted`.

A short fulfilment releases the **whole** hold, not just the shipped part — the
requisition closes, so nothing is left to justify holding the balance. That behaviour
becomes wrong if item 5 (back-orders) is ever built, and is the first thing to revisit
there.

---

## Smaller items carried over from earlier phases

Recorded so they are not lost, in rough priority order.

| Item | Note |
|---|---|
| Par levels per location | Reorder levels exist per stock level; F&B wants a par level per prep station driving suggested requisitions. |
| Decimal entry outside the movement form | PO quantity inputs are still `step="1"`. Quantities are `decimal(15,4)` throughout the backend, so this is a UI gap only. |
| Product dialog / product card stock sums | Still render whole numbers; should use `Quantity::format()` like the inventory tables do. |
| Per-variant recipes | A finished product with several variants shares one recipe. Fine for now; wrong for e.g. small/large portions with different yields. |
| Modifiers | Belongs with Phase 6 (Restaurant POS) rather than inventory. |
| Stock-take module gating | *Stock Takes* is gated behind `fnb` for consistency with Production / Requisitions / Units. Stock-taking is arguably universal — moving it to the `inventory` gate is a one-line nav change if basic-plan tenants should have it. |

---

## Explicitly out of scope

Not deferred — decided against, and listed so the question is not reopened by accident.

- **Theoretical-vs-actual usage as a separate report.** Because recipes already deplete
  ingredients at production and sale time, the system quantity *is* the theoretical
  figure. A stock count's variance is therefore the unexplained gap directly. A separate
  report would restate the same number and invite the two to disagree.
- **Batch records as the stock ledger.** `inventory_stock_levels` is authoritative;
  batches are a traceability overlay. When a location holds less batch-tracked stock than
  a movement takes — normal, since batch numbers are optional per receipt — the movement
  allocates what exists and leaves the remainder untraced rather than blocking a sale.
  Reversing this would mean every tenant had to capture batch data on every receipt.
