# Hotel Inventory and Food Production Use-Case Analysis

Date: September 5, 2026

## Purpose

This document evaluates how the current Storeboot product can support a hotel with:

- A central store.
- Departmental stores for a poolside lounge, grill, bar, and kitchens.
- Stock replenishment from the central store to those departmental stores.
- Direct resale of unchanged products such as wine and bottled drinks.
- Conversion of raw materials into finished food, such as rice into jollof rice or flour into meat pies.

This is an assessment of the current implementation. It is not an implementation specification and does not imply that the identified gaps have been built.

## Executive Conclusion

Storeboot currently provides a good foundation for central-store operations, departmental stock locations, direct stock transfers, purchasing, bar inventory, and the sale of unchanged stocked products.

It does not currently provide a proper kitchen-production system. Ingredient consumption, recipes, units of measure, production yields, wastage, and raw-material-to-finished-product costing are not represented as connected business transactions.

| Use case | Current fit |
| --- | --- |
| Central store and multiple outlet stores | Strong |
| Central-to-outlet stock transfers | Strong, but operationally basic |
| Purchasing into a selected store | Strong |
| Wine or bottled products transferred and sold unchanged | Strong |
| Stock visibility and reorder levels by location | Strong |
| Kitchen ingredient consumption | Weak and manual |
| Rice to jollof rice or flour to meat pies | Not properly supported |
| Recipe costing, production yields, and food-cost variance | Not supported |
| Hotel-style stock requisition and issue controls | Not supported |
| Bag, kilogram, gram, crate, bottle, and litre conversions | Not supported |

The current product is therefore suitable for a limited stores-and-bars deployment, but it should not yet be presented as a complete hotel food-and-beverage inventory solution.

## Current Location Model

Storeboot has four fixed inventory location types:

- `branch`
- `warehouse`
- `store_room`
- `service_unit`

These are defined by `Modules\Inventory\Enums\InventoryLocationType`.

A hotel could name and classify locations as follows:

| Hotel operation | Suggested Storeboot type |
| --- | --- |
| Central Store | Warehouse |
| Poolside Lounge Store | Service unit |
| Grill Kitchen Store | Service unit or store room |
| Main Bar Store | Service unit |
| Banquet Kitchen Store | Service unit |
| Receiving or quarantine area | Store room |

Location names are flexible, but location types are not tenant-configurable. A hotel cannot add types such as `Kitchen`, `Cold Room`, `Bar Cellar`, or `Receiving Bay` without a product change. Those descriptions can only be represented through the location name while using one of the existing types.

An inventory location may be linked to a branch or left as a standalone location. Branches are also automatically mapped to inventory locations when Inventory is enabled.

### Missing location relationships

All locations are currently peers. Storeboot has no parent-child inventory hierarchy and cannot formally represent:

```text
Hotel
└── Central Store
    ├── Poolside Lounge Store
    │   └── Poolside Kitchen
    ├── Grill Store
    └── Main Bar Store
```

There is also no replenishment rule that restricts an outlet to receiving stock from the central store. A permitted user can select any available source and destination.

## Recommended Configuration with the Current Product

The safest present-day configuration is to represent each independently controlled operation as a branch with one primary stock location:

```text
Hotel tenant
├── Central Operations branch → Central Store
├── Poolside Lounge branch → Poolside Lounge Store
├── Grill Kitchen branch → Grill Kitchen Store
├── Main Kitchen branch → Main Kitchen Store
└── Main Bar branch → Main Bar Store
```

In this design, a branch acts as an operational or cost-centre boundary even though all branches may be inside the same physical hotel.

This is preferable to placing many sales stores under a single branch because the current retail POS does not provide an explicit inventory-location selector. It filters eligible locations and submits the first matching location. Multiple possible stock locations for one till or branch can therefore cause sales to deduct from an unintended location.

Departments do not resolve this issue. Although Storeboot has department records, departments are not connected to inventory movements, purchasing destinations, sales stock deductions, or production.

This configuration has a tradeoff: branch-level accounting will treat the operational units as branches. Tenant-wide figures remain consolidated, while branch reports effectively become outlet or cost-centre reports.

## Central Store to Outlet Transfers

This use case is supported.

For a transfer, Storeboot records:

- Source inventory location.
- Destination inventory location.
- Product variant.
- Whole-number quantity.
- Current weighted-average unit cost.
- Reference number, date, and notes.
- Matching transfer-out and transfer-in movements.

The transfer process checks available stock at the source, reduces source stock, increases destination stock, carries the source average cost to the destination, and preserves total tenant inventory value.

### Direct-resale example

Wine can follow this flow successfully:

```text
Supplier delivery → Central Store
Central Store → transfer bottles to Poolside Lounge Store
Poolside Lounge POS → sell bottles
Sale → deduct bottles from Poolside Lounge Store
```

For an inventory-tracked physical product, a completed sale creates a stock-out from the selected sales location and recognizes cost of goods sold using the location's weighted-average cost.

Purchase-order lines can also specify their receiving inventory location. The hotel can therefore receive normal procurement into the central store or, when operationally appropriate, directly into an outlet store.

## Transfer Workflow Limitations

The current transfer is an immediate inventory posting rather than a hotel stores-control workflow. It has no:

- Departmental requisition.
- Requested, approved, issued, dispatched, received, or rejected status.
- Source-store approval.
- Destination receipt confirmation.
- Requested quantity versus issued quantity.
- Stock-in-transit balance.
- Partial fulfilment.
- Transfer cancellation or reversal workflow.
- Multi-product transfer document.
- Stores issue voucher.

The current form transfers one product variant at a time. A kitchen requesting twenty ingredients would require twenty separate transfer submissions.

A typical controlled hotel process cannot therefore be reproduced faithfully:

```text
Kitchen requests stock
→ Supervisor approves
→ Central store issues stock
→ Kitchen confirms receipt
→ Differences are investigated
```

## Kitchen Production and Ingredient Conversion

Kitchen production is not currently supported as a first-class Storeboot transaction.

Converting ingredients into food requires a connected production event:

```text
Inputs
- Rice
- Oil
- Tomatoes
- Seasoning
- Other ingredients

Production
- Consume actual input quantities
- Record yield, waste, and variance
- Calculate production cost

Output
- Pots, trays, batches, or portions of jollof rice
```

Storeboot currently has no data model or workflow for:

- Recipes or bills of materials.
- Recipe versions and effective dates.
- Ingredient quantities per recipe.
- Production orders or kitchen preparation batches.
- Planned versus actual consumption.
- Finished-goods output.
- Expected and actual yield.
- Prep loss, spoilage, and kitchen wastage.
- By-products.
- Work in progress.
- Production labour and overhead allocation.
- Ingredient substitution.
- Theoretical versus actual food cost.

The catalog includes `product`, `service`, and `bundle` product types, but there is no implemented bundle-component or recipe relationship that could serve as a production bill of materials.

### Why manual stock-out and stock-in are not an adequate solution

An operator could manually:

1. Stock out a bag of rice.
2. Stock in ten pots of jollof rice.

However, Storeboot would treat these as unrelated inventory adjustments. There would be no record proving that the rice and other ingredients produced those pots of jollof rice.

The accounting treatment would also be inappropriate:

- A manual reduction is posted as inventory shrinkage or write-off expense.
- A manual increase is posted as an inventory adjustment gain.
- Ingredient cost is not transferred into the finished product.

This would overstate losses and adjustment income instead of recording a legitimate raw-material-to-finished-goods conversion. It would also make finished-food margins unreliable.

## Units of Measure and Pack Conversions

Inventory, procurement, sales, and transfer quantities are stored and validated as integers. Storeboot does not currently have a unit-of-measure or pack-conversion model.

It cannot naturally express:

```text
1 bag of rice = 50 kilograms
1 kilogram = 1,000 grams
Recipe consumption = 2.5 kilograms
1 crate = 12 bottles
1 bottle = 750 millilitres
```

Products can be created in the smallest practical unit, such as grams or individual bottles, but supplier purchases would then need to be converted manually. For example, receiving one 50-kilogram bag as 50,000 gram units requires a manual conversion and cost calculation. This is operationally awkward and vulnerable to entry errors.

Fractional ingredient consumption is impossible when the product is tracked as bags or kilograms because quantities must be whole numbers.

## Batches, Expiry, and Food Traceability

Storeboot can capture a batch number and expiry date when stock enters a location. This provides basic visibility for incoming perishable goods.

However, outbound sales, consumption, and transfers update aggregate stock without selecting, depleting, or relocating a particular batch. Consequently:

- FIFO and FEFO are not enforced.
- A lot cannot be traced from the supplier through the central store to an outlet and sale.
- A batch's `quantity_remaining` can remain visible after aggregate stock has been transferred or sold.
- Expiry reporting is not an authoritative lot ledger.

This is a significant limitation for food safety, expiry control, and product recalls.

## Inventory Visibility and Reordering

The following capabilities are already useful for the hotel:

- On-hand stock by product variant and location.
- Available stock after sales reservations.
- Weighted-average unit cost by location.
- Inventory value by location.
- Low-stock thresholds and reorder quantities by location and variant.
- Inventory movement history.
- Basic damaged, returned, and expired-condition recording.
- Supplier receipts with landed-cost allocation.

The current inventory report screen is an operational summary rather than a hospitality management report. It does not calculate:

- Food cost percentage.
- Beverage cost percentage.
- Recipe margin.
- Theoretical consumption.
- Actual consumption.
- Kitchen yield.
- Portion variance.
- Outlet transfer variance.
- Waste by reason or responsible unit.

## Access and Internal Controls

Storeboot defines distinct permissions for receiving, transferring, counting, managing, and adjusting inventory. Nevertheless, all inventory movement types use a shared route that allows a user with any one of several inventory-operation permissions to access it. Only manual adjustments receive a separate controller-level permission check.

The inventory screen and controller also load tenant-wide locations and stock rather than restricting the data to the branch assigned to the current membership.

For a hotel with separate central-store, bar, and kitchen staff, this may allow broader inventory visibility or movement capability than management intends. Branch- and location-scoped authorization should be treated as a prerequisite for strong departmental controls.

## Viable Interim Pilot

A controlled pilot can reasonably cover:

- Purchasing and receiving into the central store.
- Central-store stock visibility and valuation.
- Transfer of unchanged products to outlet stores.
- Bar, lounge, and minibar bottle or can sales.
- POS deduction from an outlet's dedicated stock location.
- Low-stock thresholds by store.
- Whole-unit stock counts and write-offs.
- Supplier costing and basic movement history.

For cooked food during a pilot, the least misleading process would be:

- Keep prepared menu items as non-inventory-tracked products.
- Transfer raw ingredients from the central store to each kitchen.
- Perform regular physical ingredient counts.
- Record or calculate ingredient consumption outside the production workflow.
- Avoid presenting manual finished-food stock-in as a true production transaction.

This can support sales collection and rough ingredient control, but it will not provide dependable recipe costing, portion control, production yield, or food-cost variance.

Care is required with accounting. Using estimated COGS for non-tracked meals while separately writing off raw ingredients could double-count costs. Any interim accounting procedure must define one consistent treatment and be reviewed by the hotel's accountant.

## Capabilities Required for a Full Hotel Deployment

The principal gaps, in recommended priority order, are:

1. Decimal quantities and units of measure.
2. Purchase, stock, recipe, and sales-unit conversions.
3. Recipes or bills of materials with versioning.
4. Production orders that consume inputs and create finished outputs atomically.
5. Yield, wastage, substitution, and production-variance capture.
6. Correct raw-material, work-in-progress, and finished-goods accounting.
7. Departmental stock requisitions and approvals.
8. Dispatch, stock-in-transit, and destination receipt confirmation.
9. Multi-item transfer and issue documents.
10. Default stock location per POS till or outlet.
11. Batch-aware transfers, production consumption, and FEFO depletion.
12. Branch- and location-scoped access controls.
13. Food-cost, recipe-margin, consumption, yield, and waste reports.

## Product Positioning Recommendation

Storeboot should currently be described to the hotel as follows:

> Storeboot supports central-store purchasing, multiple departmental stock locations, stock transfers, bar inventory, direct-resale products, POS deductions, location-level valuation, and basic stock controls. Kitchen production, recipe consumption, unit conversion, requisition workflows, and finished-food costing require an additional hospitality production layer.

Recommended commercial approach:

- Proceed with a stores-and-bars pilot if the hotel accepts the documented limitations.
- Use direct-resale beverages and other unchanged products as the initial scope.
- Do not promise integrated kitchen production or recipe costing in the current release.
- Treat production, units of measure, requisitions, batch flow, and location-specific authorization as prerequisites for a full food-and-beverage rollout.

## Relevant Implementation References

- Location types: `modules/Inventory/Enums/InventoryLocationType.php`
- Location setup: `modules/Inventory/resources/views/admin/partials/location-dialog.blade.php`
- Transfer interface: `modules/Inventory/resources/views/admin/partials/transfer-dialog.blade.php`
- Movement validation: `modules/Inventory/Http/Requests/InventoryMovementRequest.php`
- Inventory movements and accounting: `modules/Inventory/Actions/PostInventoryMovementAction.php`
- Inventory tables: `modules/Inventory/database/migrations/2026_06_06_000001_create_inventory_tables.php`
- Inventory dashboard and summaries: `modules/Inventory/resources/views/admin/index.blade.php`
- Procurement receipt posting: `modules/Procurement/Actions/ReceivePurchaseOrderAction.php`
- Sales inventory deductions: `modules/Sales/Actions/CreateSalesOrderAction.php`
- Pending-order completion: `modules/Sales/Actions/CompleteSalesOrderAction.php`
- Retail POS stock-location selection: `modules/Sales/resources/views/admin/retail-pos.blade.php`
- Product types: `modules/Catalog/Enums/ProductType.php`
- Inventory permission mapping: `modules/Access/Support/RoutePermissionMap.php`
- Default inventory accounting: `modules/Finance/Actions/EnsureDefaultChartOfAccountsAction.php`
- Transfer behavior tests: `tests/Feature/InventoryOpeningStockTest.php`

## Verification Note

At the time of this analysis, the relevant inventory-opening, transfer, and sales-costing test selection completed successfully with 10 tests, 151 assertions, and no test failures. The local PHP runtime emitted a warning for an unavailable optional Swoole extension; this did not affect the test results.
