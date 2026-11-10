# Field Sales and Mobile Stock Requirements

**Status:** Requirements captured; implementation deferred  
**Captured:** October 27, 2026  
**Working module name:** Field Sales  
**Related modules:** Inventory, Sales, Customers, HR, Finance, Reporting

## 1. Purpose

Storeboot must support businesses that send products out with drivers, buses, field salespeople, campaign teams, or resellers for sale outside a branch.

The stock may remain outside the branch for several days. The custodian may sell part of it, retain the balance, receive additional stock, return some stock, record waste, and remit money at different times.

The business must always be able to determine:

- what stock left a warehouse;
- who accepted custody of it;
- which vehicle, team, reseller, or mobile location holds it;
- what is currently available with each custodian;
- what was sold, to whom, by whom, and at what price;
- what was returned, damaged, lost, expired, or otherwise adjusted;
- how much cash or credit arose from those sales;
- what stock and money remain unreconciled;
- the performance of drivers, sellers, teams, vehicles, campaigns, and resellers over time; and
- the sales, profit, collection, customer-acquisition, and variance figures used for rewards or bonuses.

## 2. Core operating principle

Stock issued for field sale remains company inventory until it is sold or otherwise disposed of.

Issuing stock to a driver or team must therefore move it from a warehouse inventory location to a controlled mobile inventory location. It must not immediately be recorded as a sale or removed from company inventory.

The module must be built on the existing inventory movement ledger rather than introducing a separate stock balance system.

## 3. Supported operating models

### 3.1 Employee or company field sales

Stock is issued to a driver, salesperson, bus, van, or campaign team. The company continues to own the stock. Sales are recorded from the mobile inventory location.

### 3.2 Reseller consignment

The company continues to own stock held by an external reseller until the reseller reports a sale. Unsold quantities remain company inventory under the reseller's custody.

### 3.3 Outright or credit reseller sale

Ownership transfers to the reseller immediately. This must be recorded as a wholesale or credit sale, not as mobile company inventory. The reseller's debt may be tracked, but the reseller's remaining stock is not company stock.

The system must require an arrangement type so consignment and outright sales cannot be mixed accidentally.

## 4. Terminology

- **Mobile location:** An inventory location representing stock outside a fixed branch, such as a bus, van, field seller, campaign team, or reseller consignment location.
- **Custodian:** The person currently responsible for stock in a mobile location.
- **Custody assignment:** A dated record linking a custodian and optional team to a mobile location.
- **Load-out:** A controlled transfer of stock from a warehouse or other approved location to a mobile location.
- **Reload:** Additional stock issued to an active mobile location without requiring all existing stock to be returned first.
- **Field session:** A period of field activity with opening stock, movements, sales, collections, closing stock, and reconciliation. A session may cover one day while stock custody continues across several days.
- **Till-in:** The process of reporting sales, counting remaining stock, returning stock where applicable, and reconciling money.
- **Reconciliation:** Comparison of expected stock and money with the quantities and amounts physically reported.
- **Campaign:** A field sales initiative that may include a team, products, territory, targets, dates, and incentive rules.

## 5. Mobile locations

### 5.1 Location types

The inventory location model must support at least:

- vehicle or bus;
- driver or field salesperson;
- campaign team;
- reseller consignment; and
- other mobile stock.

### 5.2 Required information

Each mobile location must support:

- tenant and owning branch;
- name and unique code;
- mobile-location type;
- linked vehicle, reseller, or team where applicable;
- active or inactive status;
- primary custodian;
- additional team members;
- effective custody dates;
- current stock quantity and value;
- last load-out and last reconciliation dates; and
- notes.

### 5.3 Custody history

The location and custodian must be separate concepts because drivers and team members can change.

The system must preserve a dated history showing who was responsible for a mobile location at any time. Changing a custodian must not rewrite responsibility for earlier sales, shortages, or stock movements.

## 6. Load-out requirements

### 6.1 Creating a load-out

An authorised user must be able to create a load-out containing:

- source branch and inventory location;
- destination mobile location;
- custodian, driver, or reseller;
- vehicle and team where applicable;
- load-out date and time;
- products, variants, quantities, units, batches, and expiry dates where applicable;
- expected reconciliation or return date;
- campaign or route reference where applicable;
- notes and attachments;
- user issuing the stock; and
- person accepting custody.

### 6.2 Validation and posting

The system must:

- prevent loading more than the source location's available stock;
- enforce tenant and branch access controls;
- use existing inventory transfer mechanics and valuation;
- reduce stock at the source and increase stock at the mobile location only after confirmation;
- generate a unique load-out number;
- capture the cost/value snapshot for accountability and reporting;
- support draft and approval workflows where required; and
- prevent silent editing of posted stock movements.

Corrections to posted load-outs must use a reversal or an auditable correcting movement.

### 6.3 Acceptance of custody

The receiver should be able to accept or dispute a load-out. Acceptance must capture the user, time, and quantities accepted. Differences must be resolved before or through an approved variance process.

## 7. Reload requirements

An active mobile location may receive additional stock without returning or reconciling all stock already held.

A reload must show:

- balance before reload;
- additional quantities issued;
- total quantity available afterward;
- source location;
- issuing and receiving users;
- date and time; and
- associated session, route, or campaign.

All reloads must remain traceable as separate inventory transfers.

## 8. Field sales requirements

### 8.1 Recording a field sale

A field seller must be able to record a sale against:

- tenant and branch;
- mobile inventory location;
- current custodian, driver, seller, team, and vehicle;
- session, route, or campaign;
- existing customer, newly created customer, or controlled walk-in customer;
- products and quantities;
- applicable retail, customer, group, wholesale, reseller, or customer-specific pricing;
- taxes and discounts;
- payment method and amount collected;
- credit sale information where authorised;
- date and time; and
- optional coordinates, delivery details, notes, and attachments.

### 8.2 Inventory behaviour

Field sales must reduce stock from the selected mobile location, not from the warehouse or default branch location.

The system must prevent a seller from selling more stock than is available in that mobile location. Any exceptional override must require explicit permission, a reason, and an auditable correction path.

### 8.3 Customer behaviour

The seller must be able to:

- select an existing customer;
- quickly create a customer without creating a tenant leak;
- view and apply the customer's group, pricelist, customer-specific price, and credit policy;
- view available credit before completing the sale; and
- record which customers were acquired by a driver, seller, team, route, or campaign.

### 8.4 Attribution

Each sale must preserve snapshots of the responsible custodian, seller, team, vehicle, mobile location, route, and campaign. Later reassignment must not change historical attribution.

## 9. Multi-day stock custody

The system must not require all stock to return at the end of each day.

A mobile location may retain unsold stock across multiple days. The closing stock of one session becomes the opening stock of the next session, while the underlying inventory balance remains continuous.

For each session, the system must calculate:

```text
Opening stock
+ Load-outs and reloads
+ Customer returns received
- Sales
- Returns to warehouse
- Approved waste or disposal
= Expected closing stock
```

The custodian may:

- continue with the remaining balance;
- receive more stock;
- return only part of the stock;
- transfer custody under approval; or
- return and close the mobile location's active assignment.

## 10. Stock return requirements

The user must be able to return some or all mobile stock to an authorised inventory location.

The return must:

- reference the mobile location and original load-outs where possible;
- identify the returning and receiving users;
- record product, quantity, unit, batch, and condition;
- transfer usable stock back into the selected warehouse;
- route damaged, expired, or quarantined stock appropriately;
- leave retained stock in the mobile location; and
- generate a numbered return document and audit trail.

## 11. Till-in and reconciliation

### 11.1 Stock reconciliation

The reconciliation screen must display system-expected quantities and allow entry of physically counted quantities.

It must show:

- opening stock;
- load-outs and reloads;
- sales;
- customer returns;
- warehouse returns;
- waste or disposal;
- expected closing stock;
- counted closing stock;
- shortage or excess quantity; and
- estimated value of any variance.

Any variance must require a reason and retain notes. Suggested reasons include:

- damaged;
- expired;
- customer return;
- missing or theft;
- counting error;
- data-entry error;
- transfer discrepancy;
- staff consumption;
- unknown loss; and
- other.

Large variances or selected reasons must require approval.

### 11.2 Cash reconciliation

The system must calculate expected cash separately from transfers, card payments, customer credit, and other non-cash methods.

```text
Cash sales collected
- Approved field expenses
- Cash already deposited or remitted
= Expected cash remittance
```

The reconciliation must record:

- expected cash;
- actual cash submitted;
- cash shortage or excess;
- transfer/card references;
- approved expenses;
- outstanding customer credit;
- receiver and receiving account/location;
- date and time; and
- variance reason, notes, and approval where required.

Stock reconciliation and cash reconciliation must be related but independently visible.

### 11.3 Carry-forward

Completing a daily reconciliation must not automatically return remaining stock. Reconciled closing stock may be carried forward into the next session under the same custody assignment.

## 12. Statuses

### 12.1 Load-out statuses

- Draft
- Awaiting approval
- Awaiting acceptance
- In custody
- Partially returned
- Fully returned
- Reconciled
- Cancelled
- Disputed

### 12.2 Field session statuses

- Planned
- Active
- Awaiting reconciliation
- Reconciled
- Awaiting approval
- Approved
- Disputed
- Cancelled

### 12.3 Reconciliation statuses

- Draft
- Submitted
- Approved
- Rejected
- Reopened

Status changes must be permission-controlled and audited.

## 13. Dashboard and operational views

The Field Sales dashboard must show at least:

- stock currently outside branches;
- stock quantity and value by driver, seller, team, vehicle, and reseller;
- active sessions and custodians;
- today's and period-to-date field sales;
- expected cash and cash remitted;
- overdue reconciliations;
- unresolved stock and cash variances;
- stock retained for multiple days;
- low-stock mobile locations;
- inactive mobile stock that has not moved; and
- top-performing and underperforming field sellers or teams.

Recommended module navigation:

1. Dashboard
2. Mobile Locations
3. Load-outs and Reloads
4. Field Sessions
5. Field Sales
6. Returns
7. Reconciliation
8. Reports
9. Incentives

## 14. Reports

Reports must support date, branch, mobile location, custodian, seller, team, vehicle, route, campaign, reseller, customer, product, category, and status filters where relevant.

Required reports include:

- current stock by mobile location and custodian;
- load-out and reload history;
- stock movement statement for a mobile location;
- sales by driver, seller, team, vehicle, route, or campaign;
- sales and gross profit by product and customer;
- newly acquired customers and repeat purchases;
- customer collections and outstanding credit;
- stock returned and stock retained;
- waste, shortages, excesses, and variance value;
- cash expected, remitted, and outstanding;
- overdue custody and reconciliation;
- reseller consignment statement; and
- individual and team performance over time.

Reports must follow Storeboot's standard report behaviour, including clear filters, run action, totals, export where supported, and consistent tenant isolation.

## 15. Rewards, commissions, and bonuses

The reporting foundation must support configurable incentives based on:

- revenue;
- gross profit;
- quantity sold;
- product or category targets;
- target achievement percentage;
- newly acquired customers who complete a purchase;
- collections from customers;
- promoted products or campaigns;
- low stock/cash variance; and
- timely reconciliation.

Example rules include:

- a percentage of gross profit after a sales threshold is reached;
- a fixed amount per qualifying new customer;
- a fixed amount per unit of a promoted product;
- a team bonus for reaching a campaign target; and
- withholding or reducing a bonus while material stock or cash variances remain unresolved.

Bonus calculations must initially be provisional. They become approved or payable only after the related sessions and reconciliations are approved.

The system must retain the rule and calculation snapshot used for each earned bonus so later rule changes do not alter historical results.

## 16. Roles and permissions

Suggested capabilities include:

- view field-sales dashboard;
- manage mobile locations;
- assign custodians and teams;
- create, approve, issue, accept, reverse, or cancel load-outs;
- record field sales;
- create field customers;
- issue reloads;
- return stock;
- count stock;
- submit and approve stock reconciliation;
- submit and approve cash reconciliation;
- override mobile stock restrictions;
- view cost and gross profit;
- configure incentive rules; and
- approve bonuses.

A user must only see tenants, branches, locations, customers, inventory, and staff they are authorised to access.

## 17. Audit and control requirements

The module must enforce:

- strict tenant isolation on every query, selector, request, and report;
- branch and inventory-location access control;
- no cross-custodian selling without authorised reassignment;
- no negative mobile stock under normal operation;
- acceptance of custody;
- immutable posted inventory movements;
- reversal instead of silent deletion;
- user, time, device, and source attribution for material actions;
- approval for material stock or cash variances;
- separate stock and cash accountability;
- batch, serial, and expiry tracking where the product requires it;
- preserved valuation and pricing snapshots; and
- a complete audit log of assignments, approvals, disputes, corrections, and reversals.

## 18. Integration requirements

### 18.1 Inventory

Use existing inventory locations, transfers, stock movements, valuation, batches, expiry tracking, units, and availability rules.

### 18.2 Sales and POS

Reuse the authoritative sales-order process, customer pricing, taxes, discounts, credit limits, payments, receipts, returns, and accounting entries. Field sales must specify the mobile inventory location as their stock source.

### 18.3 Customers

Reuse customer records, groups, pricelists, customer-specific pricing, credit policy, addresses, balances, and purchase history.

### 18.4 HR

Reuse organisation users, branch assignments, production/working shifts where relevant, teams, attendance, and employee identity. A field team may contain multiple users while retaining one accountable custodian or team lead.

### 18.5 Finance

Integrate cash remittance, payment accounts, receivables, expenses, shortages/excesses, inventory value, cost of goods sold, and approved bonus liabilities with the existing accounting model.

## 19. Proposed information model

Implementation may refine names, but the design is expected to require concepts equivalent to:

- mobile location profile;
- vehicle profile or reference;
- field team and team members;
- custody assignment and custody history;
- load-out header and lines;
- load-out acceptance/dispute;
- field session;
- session opening and closing stock snapshots;
- reload and return references using inventory transfers;
- field-sale attribution snapshots;
- stock reconciliation and lines;
- cash reconciliation and payment/remittance lines;
- route or campaign;
- targets and incentive rules;
- calculated and approved bonus entries; and
- audit events.

Inventory quantities must continue to come from the inventory ledger, not from duplicated balance columns in this module.

## 20. Minimum acceptance scenarios

The completed module must successfully support these scenarios:

1. A warehouse loads 80 units onto a bus and inventory immediately shows 80 units in the bus location.
2. A driver sells 25 units to named customers, and those sales reduce only the bus stock.
3. The driver retains 55 units overnight, starts the next day with those 55 units, receives 20 more, and has 75 available.
4. The driver returns 10 units while retaining 65, and both warehouse and bus balances update correctly.
5. A physical count of 64 against an expected 65 creates a one-unit shortage with value, reason, notes, and approval status.
6. Cash reconciliation separates cash, transfer, card, credit, expenses, and actual remittance.
7. Changing the driver assigned to the bus does not change historical responsibility for earlier movements or sales.
8. A consignment reseller's unsold stock remains company inventory, while an outright reseller purchase leaves company inventory immediately.
9. Customer group pricing, customer-specific pricing, and credit limits apply correctly to field sales.
10. A manager can view current stock, sales, returns, cash, variances, and overdue reconciliation for every authorised mobile location.
11. Reports attribute revenue, profit, units, customers, collections, and variances to the correct individuals and teams.
12. Provisional rewards are calculated from approved rules and are not payable until reconciliation requirements are satisfied.

## 21. Suggested implementation phases

### Phase 1: Mobile inventory and field selling

- Mobile inventory location types
- Custody assignments
- Load-outs, acceptance, and reloads
- Sales from mobile stock
- Customer attribution
- Current stock by driver, team, vehicle, and reseller
- Partial and full stock returns

### Phase 2: Sessions and reconciliation

- Multi-day custody and daily field sessions
- Opening and closing stock
- Physical stock counts
- Waste and variance reasons
- Cash remittance
- Approval and dispute workflow
- Overdue reconciliation dashboard

### Phase 3: Reporting and incentives

- Operational and performance reports
- Gross-profit and customer-acquisition attribution
- Targets and configurable incentive rules
- Provisional, approved, and payable bonuses

### Phase 4: Field-operation enhancements

- Mobile-first field interface
- Offline sale capture and controlled synchronisation
- GPS check-ins and optional location evidence
- Route and visit planning
- Customer visit schedules
- Digital signatures for stock acceptance
- Portable receipt printing and receipt sharing

## 22. Deferred decisions for implementation planning

Before implementation begins, confirm:

- whether Phase 1 must support offline operation;
- whether vehicles require a full fleet register or only a simple reference;
- whether a mobile location may have more than one accountable custodian at once;
- whether daily field sessions are mandatory or optional;
- which variance thresholds require approval;
- whether field expenses may be recorded during reconciliation;
- whether reseller consignment settlement follows cash remittance, invoicing, or both;
- whether load-out acceptance requires a PIN, signature, or normal authenticated confirmation;
- whether GPS evidence is mandatory for any business type;
- how bonuses integrate with payroll or remain report-only; and
- which reports and exports are mandatory for the first release.

## 23. Explicitly deferred

This document records requirements only. No database, routes, permissions, screens, inventory behaviour, sales behaviour, accounting entries, or reports are to be implemented until the feature is prioritised and the first implementation phase is approved.
