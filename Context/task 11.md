# Task 11: Fixed Assets, Depreciation, and Accounting Integration

## Objective

Add **Fixed Assets** as an optional Storeboot module for recording long-lived tangible assets, calculating book depreciation, tracking their custody and condition, and posting every financial event into the existing Finance ledger.

The module must answer four practical questions:

1. What fixed assets does the business own, and where are they?
2. What did each asset cost, how much has been depreciated, and what is its current carrying amount?
3. What depreciation, impairment, disposal gain, or disposal loss belongs in the accounts for a period?
4. Can every balance be traced from a report to an asset event and then to a balanced journal entry?

This document is the proposed implementation task. It is not an instruction to start coding yet.

---

## 1. Scope and Product Decision

### Recommended module boundary

Create a first-class module at:

```text
modules/FixedAssets/
├── Actions/
├── Console/Commands/
├── DTOs/
├── Enums/
├── Http/Controllers/
├── Http/Requests/
├── Jobs/
├── Models/
├── Providers/FixedAssetsServiceProvider.php
├── Support/
├── database/migrations/
├── resources/views/admin/
└── routes/admin.php
```

The module should be independently switchable by subscription, while depending on Storeboot's existing modules as follows:

```text
Business ────────┐
                 ├──> FixedAssets ───> Finance journal and reports
Finance ─────────┤
                 └──> optional Procurement source link
Procurement ─────┘
```

- **Business** supplies tenant, branch, and department context.
- **Finance** supplies chart-of-accounts records, balanced journal posting, branch ledger lines, and financial statements.
- **Procurement** is an optional acquisition source. Fixed Assets must still support direct entry and opening-balance imports.
- **Inventory must not own fixed assets.** Inventory is stock held for sale or production; computers, vehicles, furniture, machinery, and buildings are used by the business and follow a different accounting lifecycle.

Register `FixedAssetsServiceProvider` in `config/modules.php` with dependencies on `Business`, `Finance`, and `Procurement`. Add a billable module with slug `fixed-assets`. A plan may expose Fixed Assets only when Finance is also enabled.

### Phase 1 scope

- Tenant-scoped asset categories and accounting policies.
- Asset register with branch, department, custodian, serial number, asset tag, dates, costs, and status.
- Asset components with separate useful lives when a material component wears out differently.
- Manual acquisition and opening-balance registration.
- Optional link to vendor, purchase order, goods receipt, or uploaded evidence.
- Straight-line book depreciation.
- Draft, review, post, and reverse/repost workflow for monthly depreciation runs.
- Branch/department transfer history.
- Impairment and impairment reversal support.
- Disposal by sale, retirement, loss, or write-off.
- Asset register, depreciation schedule, additions, disposals, and reconciliation reports.
- Posting into Storeboot's existing journals and financial reports.
- Permissions, approvals, audit events, tenant isolation, and idempotency tests.

### Explicitly deferred

- Tax depreciation or Nigerian capital-allowance calculations.
- Declining-balance, units-of-production, or revenue-based depreciation.
- Full IAS 16 revaluation model and revaluation reserve accounting.
- IFRS 16 right-of-use assets and lease liabilities.
- Intangible assets and amortisation.
- Asset maintenance/work-order management.
- Barcode scanner/mobile stocktake-style asset verification.
- Foreign-currency asset acquisition and exchange-difference handling.
- Assets held for sale under IFRS 5.

These are separate accounting domains. The schema should leave room for them, but Phase 1 must not imply support that Storeboot does not yet provide.

---

## 2. Depreciation Explained

### What depreciation means

Depreciation is the systematic allocation of an asset's **depreciable amount** over the period in which the business expects to use it. It is not:

- a cash payment each month;
- a valuation of what the asset could sell for today;
- a savings account for replacing the asset; or
- the same as a tax capital allowance.

The basic values are:

```text
Depreciable amount = capitalised cost - residual value

Carrying amount = capitalised cost
                - accumulated depreciation
                - accumulated impairment
```

Example:

- Equipment cost: ₦1,200,000
- Expected residual value: ₦120,000
- Useful life: 36 months
- Depreciable amount: ₦1,080,000
- Approximate full-month straight-line depreciation: ₦30,000

Each posted month normally debits depreciation expense and credits accumulated depreciation. Cash does not move when depreciation is posted.

### Recognition rule

Record an item as a fixed asset when it is a tangible resource that:

- is controlled by the business;
- is used to produce or supply goods/services, rented to others, or used administratively;
- is expected to be used for more than one reporting period;
- has probable future economic benefit;
- has a reliably measurable cost; and
- meets the tenant's documented capitalisation threshold or is material by nature.

Otherwise, record the purchase as an expense or inventory, as appropriate.

The initial capitalised cost may include purchase price, non-refundable taxes, delivery, installation, professional fees, and other directly attributable costs required to bring the asset to the location and condition needed for its intended use. General administration, training, avoidable waste, routine repairs, and ordinary maintenance are normally expenses rather than part of asset cost.

### When depreciation starts and stops

- Start when the asset is **available for use**, not necessarily when it was ordered, paid for, or delivered.
- Do not depreciate land.
- Do not depreciate capital work in progress until it becomes available for use.
- Temporary idleness does not automatically stop depreciation.
- Stop when the depreciable amount has been fully allocated or when the asset is disposed of/derecognised, whichever comes first.
- A disposal date inside a month receives depreciation only through the disposal date under the recommended daily-proration policy.

The available-for-use date must therefore be separate from acquisition date and payment date.

### Recommended Phase 1 calculation policy

Use **straight-line depreciation calculated daily and posted in monthly runs**.

Store useful life in whole months. Derive a service end date from the available-for-use date plus the useful-life months. Allocate the depreciable amount in proportion to eligible calendar days across that complete service interval. This gives Storeboot:

- fair first- and final-month proration;
- deterministic handling of leap years and short months;
- no arbitrary "full month" assumption;
- an exact final minor-unit true-up; and
- a schedule that can be reproduced from stored inputs.

For each component and run period:

```text
period depreciation =
    depreciable amount × eligible service days in period ÷ total service days
```

The implementation must calculate **target accumulated depreciation through the period end**, then subtract depreciation already posted. This target-to-date approach prevents rounding drift and makes retries safe:

```text
amount to post = target accumulated depreciation - posted accumulated depreciation
```

Rules:

- Monetary values are integers in minor units; never use floating point for money.
- The final eligible period absorbs any rounding remainder.
- Accumulated depreciation can never exceed `cost_minor - residual_value_minor`, subject to impairment/disposal rules.
- Residual value cannot be negative or greater than capitalised cost.
- Useful life must be positive for depreciable components.
- The schedule must be recalculated prospectively after an approved estimate change; previously posted periods are not silently rewritten.
- A locked period cannot be changed. A correction uses an explicit reversal and a new posting in an open period.

### Useful-life and residual-value changes

Useful life and residual value are estimates. An authorised user may revise them with:

- effective date;
- old and new values;
- reason and evidence;
- requester and approver; and
- recalculated future schedule.

The change is prospective. Storeboot must preserve the old values in an immutable asset transaction/audit event instead of overwriting history without explanation.

### Book depreciation versus tax depreciation

Phase 1 calculates **book depreciation** for Storeboot's financial statements. Tax authorities may prescribe capital allowances, qualifying asset classes, rates, initial allowances, restrictions, and disposal balancing charges. Those rules differ by country and can change.

Therefore:

- do not label book depreciation as a tax deduction;
- do not use the book schedule to calculate a tax return;
- reserve a future tax-basis ledger/schedule that is separate from the book basis; and
- have country-specific tax rules reviewed by a qualified accountant or tax adviser before implementation.

### Accounting standards reference

The design follows the general cost-model concepts in [IAS 16 Property, Plant and Equipment](https://www.ifrs.org/issued-standards/list-of-standards/ias-16-property-plant-and-equipment/): tangible assets are used for more than one period, qualifying assets are initially measured at cost, and directly attributable costs can form part of that cost. The design also separates impairment because [IAS 36 Impairment of Assets](https://www.ifrs.org/issued-standards/list-of-standards/ias-36-impairment-of-assets/) requires an asset not to be carried above its recoverable amount and requires future depreciation to be adjusted after impairment.

This task is a product design, not accounting or tax advice for a particular business.

---

## 3. Accounting Integration and Journal Entries

### Chart of accounts additions

Storeboot already has `EXP-6330 Depreciation` but does not have the fixed-asset cost, accumulated depreciation, impairment, or disposal accounts needed for an asset subledger.

Add system defaults while allowing each category to map to tenant-specific accounts:

| Suggested code | Account | Type / normal balance | Purpose |
|---|---|---|---|
| `1500` | Land | Asset / debit | Non-depreciable land cost |
| `1510` | Buildings | Asset / debit | Building cost |
| `1520` | Leasehold Improvements | Asset / debit | Capitalised improvements |
| `1530` | Plant and Machinery | Asset / debit | Production equipment |
| `1540` | Furniture and Fixtures | Asset / debit | Furniture and fittings |
| `1550` | Motor Vehicles | Asset / debit | Vehicle cost |
| `1560` | Computer and Office Equipment | Asset / debit | Computers, POS hardware, office equipment |
| `1570` | Capital Work in Progress | Asset / debit | Assets not yet available for use |
| `1591`–`1596` | Accumulated Depreciation by class | Asset / credit | Contra-asset balances |
| `1598` | Accumulated Impairment | Asset / credit | Impairment contra-asset |
| `4150` | Gain on Disposal of Fixed Assets | Income / credit | Disposal proceeds above carrying amount |
| `EXP-6330` | Depreciation | Expense / debit | Existing depreciation expense account |
| `EXP-6380` | Impairment Loss | Expense / debit | Asset impairment recognised in profit/loss |
| `EXP-6390` | Loss on Disposal of Fixed Assets | Expense / debit | Carrying amount above net proceeds |

Do not use one accumulated-depreciation account for every class. Class-specific accounts make balance-sheet presentation, disposal entries, and asset-register reconciliation clearer.

### Posting design

All postings must call the existing `Modules\Finance\Actions\PostJournalEntryAction`. Do not create a second general ledger inside Fixed Assets.

Use stable source metadata for idempotency:

| Event | `source_type` | `source_id` | `source_event` |
|---|---|---:|---|
| Asset acquisition | `fixed_asset` | asset ID | `acquired` |
| Opening balance | `fixed_asset` | asset ID | `opening_balance` |
| Depreciation run | `fixed_asset_depreciation_run` | run ID | `posted` |
| Impairment | `fixed_asset_transaction` | transaction ID | `impaired` |
| Impairment reversal | `fixed_asset_transaction` | transaction ID | `impairment_reversed` |
| Branch transfer | `fixed_asset_transaction` | transaction ID | `branch_transferred` |
| Disposal | `fixed_asset_transaction` | transaction ID | `disposed` |
| Reversal | original domain source | original source ID | unique reversal event |

The current Finance unique key on tenant, source type, source ID, and source event provides duplicate-posting protection. The action should later be hardened for concurrent entry-number generation, but Fixed Assets must use its idempotency contract from day one.

### Entry 1: acquire an asset on supplier credit

Example: equipment costs ₦1,200,000 and recoverable input VAT is ₦90,000.

| Account | Debit | Credit |
|---|---:|---:|
| Plant and Machinery (`1530`) | ₦1,200,000 | — |
| Input VAT / Tax Recoverable (`1320`) | ₦90,000 | — |
| Accounts Payable (`2000`) — vendor party | — | ₦1,290,000 |

Non-recoverable tax and directly attributable delivery/installation are included in the asset's capitalised cost instead of account `1320`.

Payment of the payable remains a Procurement/Finance event:

| Account | Debit | Credit |
|---|---:|---:|
| Accounts Payable (`2000`) — vendor party | ₦1,290,000 | — |
| Bank/payment asset account | — | ₦1,290,000 |

### Entry 2: acquire and pay immediately

| Account | Debit | Credit |
|---|---:|---:|
| Relevant fixed-asset cost account | Capitalised cost | — |
| Input VAT (`1320`), if recoverable | Recoverable tax | — |
| Bank/cash/payment account | — | Total paid |

### Entry 3: monthly depreciation

For ₦30,000 depreciation assigned to the Ikeja branch:

| Account | Branch | Debit | Credit |
|---|---|---:|---:|
| Depreciation (`EXP-6330`) | Ikeja | ₦30,000 | — |
| Accumulated Depreciation—relevant class | Ikeja | — | ₦30,000 |

This increases expense in the Profit and Loss statement and reduces net fixed assets on the Balance Sheet without reducing cash.

### Entry 4: branch transfer

A department or custodian change inside one branch creates only an operational history event. A cross-branch transfer should also reclassify the gross cost and accumulated balances so branch balance sheets remain correct.

For an asset with ₦1,200,000 gross cost and ₦600,000 accumulated depreciation moving from Ikeja to Abuja:

| Account | Branch | Debit | Credit |
|---|---|---:|---:|
| Asset cost account | Abuja | ₦1,200,000 | — |
| Asset cost account | Ikeja | — | ₦1,200,000 |
| Accumulated depreciation account | Ikeja | ₦600,000 | — |
| Accumulated depreciation account | Abuja | — | ₦600,000 |

There is no tenant-wide gain or loss. Future depreciation is assigned to the new branch from the effective transfer date. Mid-period transfers require the monthly depreciation line to split by eligible days between branches.

### Entry 5: impairment

If carrying amount exceeds recoverable amount by ₦200,000:

| Account | Debit | Credit |
|---|---:|---:|
| Impairment Loss (`EXP-6380`) | ₦200,000 | — |
| Accumulated Impairment (`1598`) | — | ₦200,000 |

Storeboot then depreciates the revised depreciable carrying amount over the remaining useful life. An impairment reversal is a separate authorised transaction and may not raise carrying amount above the amount that would have existed without the impairment.

### Entry 6: disposal with a gain

Suppose original cost is ₦1,200,000, accumulated depreciation is ₦600,000, carrying amount is ₦600,000, and net cash proceeds are ₦700,000:

| Account | Debit | Credit |
|---|---:|---:|
| Bank/payment account | ₦700,000 | — |
| Accumulated Depreciation | ₦600,000 | — |
| Asset cost account | — | ₦1,200,000 |
| Gain on Disposal (`4150`) | — | ₦100,000 |

If net proceeds were ₦500,000, debit `EXP-6390 Loss on Disposal` for ₦100,000 instead. A write-off with no proceeds debits accumulated depreciation and any remaining carrying amount as a loss, then credits the original asset cost. Accumulated impairment must also be removed where present.

### Entry 7: opening asset balance

For an existing asset introduced when a tenant adopts Storeboot:

| Account | Debit | Credit |
|---|---:|---:|
| Relevant asset cost account | Historical gross cost | — |
| Accumulated depreciation account | — | Depreciation before migration |
| Opening Balance Equity (`3400`) | — | Opening carrying amount |

The import must accept an as-of date, original cost, accumulated depreciation, residual value, and remaining useful life. It must not back-post a series of fictional monthly journals into closed historical periods.

### Procurement integration

Current Storeboot purchase-order items represent product variants received into inventory locations. Fixed assets must not be pushed through that inventory path.

Implement in two steps:

1. **Phase 1:** allow manual asset registration with optional `vendor_id`, `purchase_order_id`, `goods_receipt_id`, reference number, and document evidence. The user confirms the acquisition accounting source.
2. **Phase 2:** extend Procurement with typed purchase lines (`inventory`, `expense`, `fixed_asset`). A fixed-asset receipt creates a draft asset/acquisition candidate instead of an inventory movement. Receipt approval then posts the asset/AP entry once, and Fixed Assets activates depreciation only after an available-for-use date is confirmed.

Never let both the goods receipt and asset activation post the acquisition. One domain event owns the journal; the other stores a source link.

### Financial-report effect

Because every event enters `finance_journal_entries` and `finance_journal_lines`:

- acquisition increases non-current assets and cash/AP;
- depreciation and impairment appear in Profit and Loss;
- accumulated depreciation and impairment reduce net fixed assets on the Balance Sheet;
- disposal gain/loss appears in Profit and Loss;
- acquisition and sale proceeds can be classified as investing cash flows once Storeboot's cash-flow mapping supports account classes; and
- branch journal lines feed branch profitability and branch balance reporting.

The Fixed Assets subledger must include a reconciliation report:

```text
Asset register gross cost              = fixed-asset cost GL balances
Register accumulated depreciation      = accumulated depreciation GL balances
Register accumulated impairment        = accumulated impairment GL balances
Register carrying amount               = net fixed-assets GL balance
```

Any difference is an exception that must be visible, not silently corrected.

---

## 4. Relational Database Schema Design

Use integer minor units for all money, UUID tenant ownership consistent with Storeboot, strict foreign keys, and composite indexes beginning with `tenant_id` for tenant-filtered queries.

### `fixed_asset_categories`

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `tenant_id` | UUID FK | Cascade with tenant |
| `code` | varchar(40) | Unique within tenant |
| `name` | varchar(120) | Required |
| `description` | text nullable |  |
| `asset_account_id` | bigint FK | `finance_accounts`, restrict delete |
| `accumulated_depreciation_account_id` | bigint FK nullable | Required when depreciable |
| `depreciation_expense_account_id` | bigint FK nullable | Normally `EXP-6330` |
| `accumulated_impairment_account_id` | bigint FK nullable | Normally `1598` |
| `impairment_loss_account_id` | bigint FK nullable | Normally `EXP-6380` |
| `disposal_gain_account_id` | bigint FK nullable | Normally `4150` |
| `disposal_loss_account_id` | bigint FK nullable | Normally `EXP-6390` |
| `default_method` | varchar(32) | Phase 1: `straight_line` |
| `default_useful_life_months` | unsigned smallint nullable | Null for non-depreciable class |
| `default_residual_rate_basis_points` | unsigned smallint | `0..10000` |
| `capitalisation_threshold_minor` | unsigned bigint | Tenant policy aid, not an automatic override of materiality |
| `is_depreciable` | boolean | Land/CWIP false |
| `is_active` | boolean | Indexed |
| timestamps |  |  |

Indexes and constraints:

- Unique `(tenant_id, code)`.
- Index `(tenant_id, is_active, name)`.
- Account choices must belong to the same tenant and have the expected normal balance; enforce in Form Request/Action and cover with cross-tenant tests.

### `fixed_assets`

This is the asset header and custody identity. Financial schedules live on components.

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `tenant_id` | UUID FK | Cascade with tenant |
| `fixed_asset_category_id` | bigint FK | Restrict delete |
| `branch_id` | bigint FK | Restrict delete while asset is active |
| `department_id` | bigint FK nullable | Null on delete or restrict according to Business policy |
| `custodian_user_id` | bigint FK nullable | Null on delete |
| `vendor_id` | bigint FK nullable | Restrict/null according to Procurement policy |
| `asset_number` | varchar(80) | Human-readable, tenant unique |
| `asset_tag` | varchar(100) nullable | Tenant unique when present |
| `name` | varchar(180) | Required |
| `description` | text nullable |  |
| `serial_number` | varchar(160) nullable | Indexed per tenant |
| `status` | varchar(32) | `draft`, `capital_work_in_progress`, `active`, `disposed`, `written_off` |
| `acquired_on` | date | Required before activation |
| `available_for_use_on` | date nullable | Starts depreciation |
| `disposed_on` | date nullable | Required for terminal states |
| `source_type` | varchar(80) nullable | e.g. `goods_receipt` or `opening_import` |
| `source_id` | unsigned bigint nullable | Source record |
| `reference_number` | varchar(120) nullable | Invoice/receipt reference |
| `notes` | text nullable |  |
| `created_by`, `updated_by` | bigint FK | Audit actors |
| timestamps |  |  |

Indexes:

- Unique `(tenant_id, asset_number)`.
- Unique `(tenant_id, asset_tag)` with nullable behavior verified for MySQL.
- Index `(tenant_id, status, branch_id)`.
- Index `(tenant_id, fixed_asset_category_id, status)`.
- Index `(tenant_id, serial_number)`.
- Index `(tenant_id, available_for_use_on, status)`.
- Index `(tenant_id, source_type, source_id)`.

### `fixed_asset_components`

Every asset has at least one component. A simple laptop has one component; a building may have structure, lifts, and air-conditioning with different useful lives.

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `tenant_id` | UUID FK | Cascade with tenant |
| `fixed_asset_id` | bigint FK | Cascade with asset |
| `name` | varchar(160) | Required |
| `sequence` | unsigned smallint | Stable display order |
| `cost_minor` | unsigned bigint | Capitalised cost |
| `residual_value_minor` | unsigned bigint | Must not exceed cost |
| `depreciation_method` | varchar(32) | Phase 1 `straight_line` |
| `useful_life_months` | unsigned smallint nullable | Null only if non-depreciable |
| `available_for_use_on` | date nullable | Defaults from asset, may differ by component |
| `service_end_on` | date nullable | Persisted result for schedule reproducibility |
| `asset_account_id` | bigint FK | Snapshot of the category mapping used at activation |
| `accumulated_depreciation_account_id` | bigint FK nullable | Snapshot used for depreciation and disposal |
| `depreciation_expense_account_id` | bigint FK nullable | Snapshot used for depreciation |
| `accumulated_impairment_account_id` | bigint FK nullable | Snapshot used for impairment and disposal |
| `impairment_loss_account_id` | bigint FK nullable | Snapshot used for impairment |
| `disposal_gain_account_id` | bigint FK nullable | Snapshot used for disposal |
| `disposal_loss_account_id` | bigint FK nullable | Snapshot used for disposal |
| `accumulated_depreciation_minor` | unsigned bigint | Cached summary maintained only by posting actions |
| `accumulated_impairment_minor` | unsigned bigint | Cached summary maintained only by posting actions |
| `version` | unsigned integer | Optimistic concurrency |
| timestamps |  |  |

Indexes:

- Unique `(fixed_asset_id, sequence)`.
- Index `(tenant_id, available_for_use_on)`.
- Check constraints for residual/cost and accumulated balances where supported; repeat as domain validation.

The cached accumulated amounts are deliberate denormalisation for fast asset lists. The immutable transactions and depreciation lines are authoritative and must reconcile to the cache.

Account mappings are copied from the category onto each component at activation. Changing a category later affects future assets only; it must not silently redirect depreciation or disposal postings for existing assets. Moving an existing component to different accounts requires an explicit, balanced reclassification transaction.

### `fixed_asset_depreciation_runs`

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `tenant_id` | UUID FK | Cascade with tenant |
| `period_start`, `period_end` | date | Required |
| `version` | unsigned smallint | Starts at 1 and increments after a reversal/correction |
| `status` | varchar(24) | `draft`, `reviewed`, `posted`, `reversed` |
| `total_minor` | unsigned bigint | Sum of lines |
| `finance_journal_entry_id` | bigint FK nullable | Restrict delete after posting |
| `prepared_by`, `reviewed_by`, `posted_by` | bigint FK nullable | Separation of duties |
| `prepared_at`, `reviewed_at`, `posted_at` | timestamp nullable | Audit timeline |
| `reversal_journal_entry_id` | bigint FK nullable | Restrict delete |
| `notes` | text nullable |  |
| timestamps |  |  |

Indexes:

- Unique `(tenant_id, period_start, period_end, version)`. Application rules permit only one non-reversed version for a period.
- Index `(tenant_id, status, period_end)`.

### `fixed_asset_depreciation_lines`

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Primary key |
| `tenant_id` | UUID FK | Cascade with tenant |
| `fixed_asset_depreciation_run_id` | bigint FK | Cascade only while draft; application prevents deleting posted runs |
| `fixed_asset_id` | bigint FK | Restrict delete |
| `fixed_asset_component_id` | bigint FK | Restrict delete |
| `branch_id` | bigint FK | Branch receiving the expense |
| `eligible_days` | unsigned smallint | Calculation evidence |
| `opening_carrying_amount_minor` | unsigned bigint | Snapshot |
| `depreciation_minor` | unsigned bigint | Amount posted |
| `closing_carrying_amount_minor` | unsigned bigint | Snapshot |
| `calculation_snapshot` | JSON | Method, dates, life, residual value, and formula inputs |
| timestamps |  |  |

Indexes:

- Unique `(fixed_asset_depreciation_run_id, fixed_asset_component_id, branch_id)` unless a mid-period transfer requires more than one branch segment; in that case include a sequence/segment date.
- Index `(tenant_id, fixed_asset_id, fixed_asset_depreciation_run_id)`.

### `fixed_asset_transactions`

Immutable subledger/audit events for `acquisition`, `opening_balance`, `cost_adjustment`, `estimate_change`, `transfer`, `impairment`, `impairment_reversal`, `disposal`, `write_off`, `depreciation`, and `reversal`.

Important columns:

- tenant, asset, and optional component IDs;
- effective date and event type;
- gross cost, depreciation, impairment, proceeds, gain/loss amounts in minor units;
- from/to branch, department, and custodian where applicable;
- related depreciation run and journal entry IDs;
- source type/ID and reversal-of transaction ID;
- reason, evidence metadata, requester, approver, and timestamps.

Use indexes on `(tenant_id, fixed_asset_id, effective_date, id)`, `(tenant_id, type, effective_date)`, and `(tenant_id, source_type, source_id)`. A posted transaction is never edited or deleted; corrections are new linked transactions.

### Attachments

Use a shared attachment/document capability if Storeboot adds one. Otherwise create `fixed_asset_documents` with tenant, asset, transaction, type, storage disk/path, original filename, MIME type, size, checksum, uploader, and timestamps. Files must be private and served through an authorised download route, never a public predictable URL.

---

## 5. Laravel Eloquent Models and Relationships

Use Storeboot's `BelongsToTenant` concern and `$guarded = []` only when all writes pass through validated Form Requests and Actions. Controllers remain thin.

```php
final class FixedAsset extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'available_for_use_on' => 'date',
            'disposed_on' => 'date',
            'status' => FixedAssetStatus::class,
        ];
    }

    public function category(): BelongsTo { /* ... */ }
    public function branch(): BelongsTo { /* ... */ }
    public function department(): BelongsTo { /* ... */ }
    public function custodian(): BelongsTo { /* ... */ }
    public function components(): HasMany { /* ... */ }
    public function transactions(): HasMany { /* ... */ }
    public function documents(): HasMany { /* ... */ }
}
```

```php
final class FixedAssetComponent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_for_use_on' => 'date',
            'service_end_on' => 'date',
        ];
    }

    public function asset(): BelongsTo { /* ... */ }
    public function depreciationLines(): HasMany { /* ... */ }
    public function transactions(): HasMany { /* ... */ }
}
```

Other models:

- `FixedAssetCategory belongsTo` its configured Finance accounts and `hasMany FixedAsset`.
- `FixedAssetDepreciationRun hasMany FixedAssetDepreciationLine` and belongs to posting/reversal journal entries and actor users.
- `FixedAssetDepreciationLine belongsTo` run, asset, component, and branch.
- `FixedAssetTransaction belongsTo` asset, optional component, journal entry, source transaction, requester, and approver.

All relationship queries used by list/report screens must eager-load explicitly. Enable `Model::preventLazyLoading()` outside production first, then in all environments once existing violations are resolved.

---

## 6. High-Performance Backend Business Logic

### Main Actions

Create small Actions with one responsibility:

- `CreateFixedAssetAction`
- `ActivateFixedAssetAction`
- `RecordFixedAssetAcquisitionAction`
- `ImportOpeningFixedAssetAction`
- `PrepareDepreciationRunAction`
- `PostDepreciationRunAction`
- `ReverseDepreciationRunAction`
- `TransferFixedAssetAction`
- `ChangeDepreciationEstimateAction`
- `RecordImpairmentAction`
- `ReverseImpairmentAction`
- `DisposeFixedAssetAction`
- `ReconcileFixedAssetLedgerAction`

DTOs should carry validated typed values between requests and actions. Do not pass raw request arrays deep into the domain.

### Depreciation calculator

Keep calculation pure and independently testable:

```php
interface DepreciationCalculator
{
    public function calculate(
        FixedAssetComponentSnapshot $component,
        DatePeriod $period,
        Money $alreadyPosted,
    ): DepreciationResult;
}
```

`StraightLineDepreciationCalculator` must return:

- eligible start/end dates and days;
- depreciable amount;
- target accumulated depreciation;
- current-period amount;
- opening and closing carrying amounts; and
- a serialisable calculation snapshot.

Future methods can be registered behind the interface without changing posting orchestration.

### Preparing a run

`PrepareDepreciationRunAction` should:

1. Validate the tenant and open accounting period.
2. Create or refresh the tenant's draft run under a transaction.
3. Query active depreciable components in chunks with required category/account/branch relations eager-loaded.
4. Resolve branch segments for any mid-period transfer.
5. Calculate lines using the pure calculator.
6. Store calculation snapshots and total.
7. Surface exceptions, such as missing account mappings, invalid dates, or residual value above carrying amount.
8. Leave the run unposted for review.

Draft runs may be recalculated. Reviewed or posted runs may not.

### Posting a run

`PostDepreciationRunAction` should use one database transaction and:

1. Lock the run and relevant component rows with `lockForUpdate()`.
2. Confirm it is reviewed, still current, and not already posted.
3. Revalidate permissions, account mappings, tenant ownership, open period, and calculation version.
4. Group lines by branch, depreciation expense account, and accumulated-depreciation account.
5. Call `PostJournalEntryAction` with balanced debit/credit lines.
6. Create immutable depreciation transactions.
7. Increment component cached accumulated depreciation in integer minor units.
8. Link the journal, mark the run posted, and commit.

If anything fails, none of the run, subledger, cache, or GL should be partially posted.

Illustrative orchestration:

```php
return DB::transaction(function () use ($run): FixedAssetDepreciationRun {
    $run = FixedAssetDepreciationRun::query()
        ->where('tenant_id', $run->tenant_id)
        ->lockForUpdate()
        ->findOrFail($run->id);

    $this->postingGuard->assertPostable($run);
    $lines = $this->journalLines->forRun($run);

    $journal = $this->postJournalEntry->execute(
        $run->tenant_id,
        $run->period_end->toDateString(),
        'Fixed asset depreciation '.$run->period_end->format('Y-m'),
        $lines,
        'fixed_asset_depreciation_run',
        $run->id,
        'posted',
    );

    $this->subledger->recordPostedRun($run, $journal);

    return $run->refresh();
}, attempts: 3);
```

### Period control prerequisite

Storeboot does not currently expose a formal accounting-period lock in the inspected Finance schema. Add a Finance period-control capability before allowing production depreciation posting:

- tenant and period start/end;
- status `open`, `soft_closed`, or `locked`;
- actor and timestamp;
- permission-controlled reopen with audit trail.

Without this, users could post or reverse depreciation in a finalised period and change previously issued reports.

### Reversal behavior

Never delete a posted journal, run, or transaction. Reversal creates:

- an equal and opposite journal dated in an allowed period;
- reversing subledger transactions;
- cached-balance adjustments under row locks; and
- a link between original and reversal.

If the original period is locked, post the correction in the current open period and disclose the original affected period in the memo.

---

## 7. UX Strategy and Flow

### Navigation

Add a top-level **Fixed Assets** area visible only when:

- the tenant has the `fixed-assets` module entitlement; and
- the user has `fixed-assets.view`.

Recommended navigation:

- Overview
- Asset register
- Depreciation runs
- Categories & policies
- Reports

Keep Finance's Journals and Report pages unchanged; they will naturally show the module's postings.

### Primary journey: add and activate an asset

Use a progressive, four-step flow:

1. **Identity and location** — name, category, tag, serial, branch, department, custodian.
2. **Acquisition and cost** — acquisition date, vendor/source, price, recoverable tax, directly attributable costs, payment/AP account, evidence.
3. **Depreciation policy** — available-for-use date, component(s), residual value, useful life, method, preview schedule.
4. **Review and post** — show the exact acquisition journal and explain when depreciation begins.

Saving a draft must not post accounting. Activation requires complete accounting data and explicit confirmation. If approval policy applies, activation creates an approval request rather than posting directly.

### Primary journey: monthly depreciation

1. User selects a month and clicks **Prepare run**.
2. Storeboot displays asset/component count, total depreciation, branch breakdown, category breakdown, and exceptions.
3. User resolves exceptions and recalculates the draft.
4. Reviewer marks the run reviewed.
5. Authorised poster sees the exact journal summary and clicks **Post depreciation**.
6. Storeboot links to the resulting journal and locks the run against editing.

The person preparing the run should not approve/post it when separation-of-duties policy is enabled.

### Asset detail page

Header:

- asset name, tag, status, category, branch, and carrying amount;
- primary actions based on status and permission;
- warning banner for missing evidence, overdue review, or reconciliation difference.

Tabs:

- Summary
- Components & depreciation schedule
- Transactions
- Location & custody history
- Documents
- Accounting entries

Every monetary total should link or drill down to its source transaction and journal.

### Dashboard

Show:

- gross asset cost;
- accumulated depreciation;
- accumulated impairment;
- net carrying amount;
- current-month depreciation status and amount;
- additions and disposals in the selected period;
- assets by category and branch;
- assets missing tags/custodians/documents;
- assets approaching the end of useful life; and
- subledger-to-GL reconciliation status.

Avoid implying that carrying amount is current market value.

---

## 8. Visual and Structural Specifications

- Use the existing admin shell and Blade/Tailwind-style visual language rather than introducing a second UI system.
- Desktop list pages use a filter bar, summary strip, and responsive table. Mobile uses stacked asset cards with the same information order.
- Keep the primary action in the page header; destructive/terminal actions stay in a labelled overflow menu.
- Use `p-6`, `gap-4`, and existing Storeboot panel/card tokens for normal desktop density; collapse to `p-4` on small screens.
- Right-align money and use tabular numerals. Display tenant currency while retaining integer minor units internally.
- Status must use text plus icon, never colour alone.
- Dialogs require labelled controls, focus trapping, Escape/close handling, and focus restoration.
- Tables require headers, meaningful empty states, keyboard-reachable row actions, and horizontal-scroll affordances.
- Loading states use skeletons for summaries and rows; posting actions use a progress state and disable duplicate submission.
- A posted state must provide the journal number and a direct link instead of only a transient success message.

### Empty states

- No assets: explain what qualifies as a fixed asset and offer **Add first asset** or **Import opening assets**.
- No depreciation run: explain that a run is prepared, reviewed, then posted monthly.
- No category: require category/account setup before asset activation, with a direct setup link.
- No permission: hide mutation controls and show read-only information; never rely on UI hiding for server security.

### Extreme and error states

- Very large money values must wrap safely without scientific notation.
- Long asset names, tags, and serials must wrap or truncate with accessible full text.
- A run with thousands of assets must paginate its review lines and retain filters.
- If one component is invalid, prepare the valid draft lines but block posting the entire run and show a downloadable exception list.
- On a stale/version conflict, do not overwrite; reload and explain which asset changed.
- On journal-posting failure, roll back all domain mutations and preserve the draft for retry.

---

## 9. Permissions and Approval Controls

Add a Fixed Assets section to `PermissionCatalogue`:

- `fixed-assets.view`
- `fixed-assets.create`
- `fixed-assets.update`
- `fixed-assets.categories.manage`
- `fixed-assets.depreciation.prepare`
- `fixed-assets.depreciation.review`
- `fixed-assets.depreciation.post` — sensitive
- `fixed-assets.acquisitions.post` — sensitive
- `fixed-assets.transfer`
- `fixed-assets.estimates.change` — sensitive
- `fixed-assets.impair` — sensitive
- `fixed-assets.dispose.request`
- `fixed-assets.dispose.approve` — sensitive
- `fixed-assets.dispose.post` — sensitive
- `fixed-assets.reconcile`
- `fixed-assets.reports.export` — sensitive

Recommended role behavior:

- Owner: full access.
- Accountant: categories, accounting, runs, reconciliation, and reports.
- Asset/Inventory Officer: register, tag, transfer, custody, and physical details; no journal posting by default.
- Branch Manager: view and request transfers/disposals within assigned branches.
- Cashier: no access by default.

Branch restrictions apply to asset visibility and operations as well as journal lines. A branch-scoped user must not discover another branch's assets through IDs, exports, search, or attachment routes.

---

## 10. Scalability, Caching, and Queue Blueprint

### Query and batching strategy

- Filter every query by tenant before other conditions.
- Eager-load category/account/branch data for run preparation.
- Process components with `chunkById()` to avoid loading a large register into memory.
- Aggregate dashboard values in SQL rather than PHP collections.
- Paginate register, transactions, schedules, and exceptions.
- Use streamed CSV exports or queued spreadsheet generation for large reports.

### Queue usage

Small depreciation runs may prepare synchronously. Large runs dispatch `PrepareDepreciationRunJob` by tenant and period. Posting itself remains a controlled database transaction initiated only after review.

The scheduler may notify tenants that a period is ready, but it must not auto-post in Phase 1. Later, auto-posting can be an explicit tenant policy with strong audit and failure notifications.

### Cache keys

Cache read summaries only, never source accounting truth:

```text
tenant:{tenantId}:fixed-assets:summary:v1
tenant:{tenantId}:fixed-assets:category-summary:{asOfDate}:v1
tenant:{tenantId}:fixed-assets:branch-summary:{branchId}:{asOfDate}:v1
tenant:{tenantId}:fixed-assets:reconciliation:{asOfDate}:v1
```

Invalidate after acquisition, activation, estimate change, transfer, depreciation posting/reversal, impairment, and disposal. Prefer tagged cache when the configured driver supports it; otherwise increment a tenant fixed-assets cache version.

Do not cache mutable tenant or actor state in Octane singletons. Calculators may be stateless singletons; request/tenant context may not.

### Concurrency

- Lock the asset/component for terminal events and estimate changes.
- Lock the run and components when posting or reversing.
- Use optimistic `version` checks on interactive edits.
- Use unique source metadata and unique period constraints as database-level idempotency protection.
- Retry deadlocks a small bounded number of times.
- Prevent disposal while a depreciation run containing the asset is being posted, and prevent run posting against a component version that changed after preparation.

---

## 11. Reports

Fixed Assets reports:

1. **Asset register** — cost, accumulated depreciation, impairment, carrying amount, category, branch, status, custodian.
2. **Depreciation schedule** — opening balance, additions, depreciation, impairment, disposals, closing balance by asset/component.
3. **Depreciation expense** — period totals by branch, department, and category.
4. **Additions report** — acquired/activated assets and source references.
5. **Disposals report** — proceeds, carrying amount, gain/loss, and approval trail.
6. **Transfer/custody report** — location and custodian history.
7. **Fully depreciated assets still in use** — operational review list.
8. **Asset-to-GL reconciliation** — subledger versus Finance accounts with drill-down differences.
9. **Asset roll-forward** — opening gross cost and accumulated depreciation, additions, disposals, current depreciation, impairment, and closing balances.

Reports must support date, branch, category, status, and department filters; onscreen review; CSV/Excel export subject to permission; and clear "as of" dates.

---

## 12. Testing Strategy and Acceptance Criteria

### Unit tests

- Straight-line schedule for full, partial, leap-year, and final periods.
- Residual value and non-depreciable asset behavior.
- Minor-unit rounding and exact final true-up.
- Target-to-date calculation after previous postings.
- Prospective estimate change.
- Impairment and reversal ceiling.
- Disposal gain/loss calculation.
- Mid-month cross-branch allocation.

### Feature/integration tests

- Tenant cannot view, mutate, export, or attach files to another tenant's asset.
- Branch-scoped user cannot access another branch's asset.
- Draft asset creates no journal.
- Acquisition creates exactly one balanced journal.
- Retrying acquisition or run posting creates no duplicate journal.
- Posted depreciation updates the subledger cache and GL atomically.
- Failed journal posting leaves run and component balances unchanged.
- Prepared run becomes stale after a component change and cannot post.
- Posted run cannot be edited/deleted.
- Reversal creates opposite entries and preserves original history.
- Land and CWIP do not depreciate.
- Activation starts at available-for-use date.
- Disposal removes gross cost and contra balances and records the correct gain/loss.
- Finance P&L and Balance Sheet totals reflect asset postings.
- Asset register reconciles to the GL.
- Disabled module and missing permission block both UI and direct route access.

### Acceptance criteria

The first release is complete when:

- a tenant can configure an asset category with valid Finance account mappings;
- a user can create, review, approve, and activate an asset without using Inventory;
- Storeboot previews the full depreciation schedule before posting;
- an authorised user can prepare, review, and post a monthly run;
- the posted journal is balanced, idempotent, branch-aware, and traceable to every run line;
- assets can be transferred, impaired, disposed, and corrected without deleting posted history;
- financial statements and asset reports agree; and
- automated tests prove tenant isolation, permissions, rounding, concurrency guards, and accounting entries.

---

## 13. Implementation Phases

### Phase 0 — accounting decisions and prerequisites

- Confirm book basis, default currency behavior, capitalisation policy, and straight-line daily proration.
- Have an accountant review default account names, useful-life guidance, and opening-balance workflow.
- Add Finance accounting-period controls.
- Decide whether Fixed Assets is included in existing plans or sold separately.

### Phase 1 — foundation and asset register

- Module provider, routes, entitlement, navigation, permissions.
- Categories, account mapping, assets, components, transactions, documents.
- Draft/activate flow and manual/opening acquisition journals.
- Asset register/detail screens and audit history.

### Phase 2 — depreciation

- Pure calculator and exhaustive tests.
- Prepare/review/post/reverse runs.
- Journal integration, branch splitting, schedule/report screens.
- Scheduler notifications and queued preparation for large tenants.

### Phase 3 — lifecycle events

- Transfers and custody history.
- Estimate changes.
- Impairment and reversal.
- Disposal, sale, retirement, and write-off.

### Phase 4 — procurement and reconciliation

- Typed Procurement lines for inventory, expense, and fixed asset.
- Draft asset creation from received fixed-asset lines.
- Asset-to-GL reconciliation and exception workflow.
- Roll-forward and export reports.

### Phase 5 — hardening and rollout

- Load/concurrency tests.
- Accessibility and responsive QA.
- Opening-register import template, validation preview, and rollback-safe import.
- Pilot with sample tenants before enabling plan-wide.
- Operational documentation for month-end depreciation and disposal approval.

---

## 14. Decisions Required Before Coding

The recommended defaults are included so development can proceed once approved:

| Decision | Recommendation |
|---|---|
| Module packaging | Separate optional `fixed-assets` module; require Finance |
| Book depreciation method | Straight line only in Phase 1 |
| Proration | Daily calculation, monthly posting |
| Posting workflow | Prepare → review → post; no Phase 1 automatic posting |
| Acquisition workflow | Manual/opening first; typed Procurement lines later |
| Components | Support from the first schema, with one default component |
| Revaluation | Defer |
| Tax depreciation | Separate future country-specific subledger |
| Corrections | Reversal and repost; never edit/delete posted records |
| Branch transfer | Reclassify cost and contra balances across branch journal lines |
| Accounting periods | Add Finance period locks before production posting |

The most important product guardrail is this: **the asset register is a subledger, while Finance remains the general ledger**. Every fixed-asset balance must reconcile to Finance, and no event may be posted independently in both modules.
