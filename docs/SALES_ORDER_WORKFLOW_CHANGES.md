# Sales and Order Workflow Changes

Date: July 29, 2026

## Request summary

The sales workflow needed a clearer distinction between a completed sale and a customer order that is still pending. The requested outcome was one **Record a Sale** menu that can:

- Record an immediately completed offline sale.
- Record a pending customer order that may be unpaid, partially paid, or fully paid.
- Avoid selecting a payment method before the customer has actually paid.
- Keep storefront orders pending even after online payment until the order is fulfilled and explicitly completed.
- Post accounting and inventory only when the transaction becomes a completed sale.
- Keep POS and immediately completed sales posting at creation without posting them again.
- Make order completion safe, confirmed, subscription-aware, and idempotent.

The order listing and order-view dialog also needed clearer status controls, actions, source information, filtering, and date/time display.

## Record a Sale workflow

The Record a Sale screen now includes a **Record as** choice:

- **Completed Sale** creates the order as Completed and uses the existing immediate sales workflow.
- **Customer Order** creates the order as Pending and defers sales accounting and inventory deduction until completion.

Customer orders require a named customer rather than the walk-in customer.

### Customer-order payment choice

When Customer Order is selected, the form asks:

> Has the customer paid anything?

The available answers are:

- **No — payment not received**
- **Yes — record payment now**

When No is selected:

- Payment method, receiving account, amount paid, and change fields are hidden and disabled.
- The submitted amount is forced to zero by the backend.
- The payment method is cleared by the backend.
- No payment record is created.
- The order is created as Unpaid.
- Stale or manipulated payment values cannot accidentally create a payment.

When Yes is selected:

- Payment details are displayed.
- A payment method is required.
- Zero, partial, or full payment can be recorded for the customer order.

Completed Sale retains its existing payment behavior and requires full payment unless it is explicitly recorded as a credit sale.

## Order, payment, and delivery statuses

Order, payment, and delivery statuses represent separate parts of the transaction.

### Order status

- Pending
- Processing
- Completed
- Cancelled
- Partially Returned
- Returned

The unused Draft status was removed and existing Draft records are migrated to Pending. Processing was added to represent an order being prepared or fulfilled.

### Delivery status

- Pending
- Processing
- Out for Delivery
- Delivered
- Failed Delivery
- Returned

Delivery status describes fulfilment. Order status describes the overall commercial lifecycle.

### Payment status

The payment statuses continue to describe the financial position independently:

- Pending
- Unpaid
- Partially Paid
- Paid
- Customer Credit
- Refunded
- Partially Refunded

## Completing a pending order

Moving an order from Pending or Processing to Completed now runs a dedicated completion workflow.

The browser asks:

> Are you sure you want to complete Order SO-123?

On the first successful completion, the system:

- Changes the order to Completed.
- Posts received payments to their actual cash, bank, card, or gateway account.
- Posts any unpaid balance to Accounts Receivable.
- Marks a completed order with an outstanding balance as a credit sale.
- Updates the customer's outstanding balance.
- Posts sales revenue, tax, shipping, and discounts.
- Posts COGS and inventory accounting when applicable.
- Deducts stock when Inventory is enabled.

Payments recorded while the order was Pending are stored without accounting entries. They are included in the sales journal when the order is completed. Payments recorded after completion use the normal Cash/Bank versus Accounts Receivable payment journal.

Completion is idempotent: submitting Completed again does not create another sales journal, stock movement, or customer balance. A Completed order cannot be moved backwards to Pending or Processing.

## Subscription-aware inventory behavior

The completion and immediate-sale workflows now check whether the tenant is entitled to the Inventory module.

### Inventory subscribed

- An active inventory location is required.
- For online orders without a stored location, the completion process selects an active location belonging to the fulfilment branch.
- Stock is deducted at completion.
- Average inventory cost is used for COGS when available.
- Estimated product cost is used as a fallback only when the tenant has enabled **Use Estimated Cost for COGS**.
- The journal debits COGS and credits Inventory.

### Inventory not subscribed

- An inventory location is not required.
- No inventory movement is created.
- Revenue, payment, tax, shipping, discount, and receivable accounting still posts.
- When **Use Estimated Cost for COGS** is enabled, estimated product cost is used for COGS.
- When estimated COGS is disabled, COGS and inventory-value lines are omitted.

This behavior also allows an immediately completed offline sale to be recorded without an inventory location when Inventory is not included in the tenant's subscription.

## POS, completed sales, and online orders

### POS and immediately completed sales

POS and Record as Completed Sale orders are created directly as Completed. Their inventory movement and accounting journal are posted during creation. The deferred completion process does not repost them.

### Offline customer orders

Offline Customer Orders begin as Pending. Accounting and inventory are deferred until the first transition to Completed.

### Online storefront orders

Storefront orders remain Pending after successful online payment. Payment confirmation updates the payment status but does not mark the order Completed.

When the online order is completed:

- Paystack and other online receipts post to the payment-gateway clearing account.
- Inventory is deducted from an active location belonging to the fulfilment branch when Inventory is enabled.

## Order listing changes

The order listing now includes:

- Order source: POS, Online, or Offline.
- Time displayed before the date, for example `3:34 PM · Jul 29, 2026`.
- Filters for branch, source, order status, and payment status.
- A responsive three-column filter layout rather than the earlier two-column layout.

The listing keeps only the View action. Other order actions were moved into the order-view dialog.

## Order-view dialog changes

The top of the dialog now contains icon buttons for:

- Cancel Order
- Return Order
- Generate Receipt
- Generate Invoice

Order status and delivery status each have their own selector and update button inside the dialog.

The bottom of the dialog contains only the Close button.

The sales status-update route was added to the permission map so authorized tenant users with `sales.update` no longer receive an incorrect 403 response.

## Cancellation, return, and refund behavior

Cancel Order does not process a product return.

- Cancellation is available for Pending or Processing orders.
- If a pending order has received payment, cancellation converts the unrefunded amount to Customer Credit.
- The separate refund action clears that customer credit and posts the refund.
- Return Order remains the workflow for returning goods from a completed sale and updating inventory/refund information.

## Storefront checkout changes

After checkout:

- The cart is cleared.
- Paid storefront orders remain Pending rather than becoming Completed.
- Continue Shopping closes the checkout/cart state and navigates back to the store so a new shopping session can begin.

## Database changes

The following migrations support the workflow:

- `2026_07_28_000001_replace_draft_sales_order_status.php`
  - Converts existing Draft orders to Pending.
- `2026_07_28_000002_add_inventory_location_to_sales_orders.php`
  - Stores the inventory location associated with a sales order.
  - The field is nullable for online orders and tenants without Inventory.

## Verification

Focused sales, status, cancellation, costing, and storefront tests passed:

- 28 tests
- 324 assertions

The regression coverage includes:

- Pending customer orders without payment.
- Protection against stale payment fields.
- Deferred payment accounting.
- Inventory-enabled completion.
- Completion without an Inventory subscription.
- Optional estimated COGS.
- Prevention of duplicate completion postings.
- Prevention of moving a Completed order backwards.
- Permission-controlled status updates.
- Storefront pending-order and cart behavior.

## Follow-up accounting and inventory fixes (July 29, 2026)

These changes close revenue-integrity gaps found while reviewing the workflow above.

### Gateway surcharge now balances online completion

Online orders carry a payment-gateway surcharge inside the order total. Completion previously debited the full amount received but had no matching credit for the surcharge, so completing a paid online order threw "Journal entry is not balanced" and its revenue, COGS, and inventory could never post.

- A new income account **`4130` Payment Gateway Charge Recovered** was added to the default chart of accounts.
- Completion now credits the surcharge to `4130`, so the entry balances and the surcharge is recognised as recovery income. Actual gateway/settlement fees continue to post to `EXP-6350`.
- `4130` (and `2310` below) are seeded for new tenants automatically and backfilled for existing tenants by a migration.

### Deposits liability keeps pending-order money on the ledger

Payments taken while an order is Pending were previously stored with no journal entry, so real cash and online receipts sat off the general ledger until completion.

- A new liability account **`2310` Customer Deposits (Unearned Revenue)** was added.
- Recording a payment on a pending order now posts **debit cash/gateway clearing, credit `2310`**, so the asset and the deposit liability are recognised immediately.
- Completion clears the deposit: it **debits `2310`** for the amount already received and books any unpaid balance to Accounts Receivable, then recognises revenue, tax, shipping, gateway recovery, and COGS.
- Cancelling a paid pending order moves the deposit from `2310` to Customer Credits (`2300`) instead of creating a phantom receivable.

### Stock reservation prevents online overselling

With Inventory enabled, stock is now reserved rather than left unprotected until completion.

- Online checkout reserves stock at the fulfilment branch's active location. If the last unit is already reserved, checkout is rejected with a JSON error so two shoppers cannot buy the same unit.
- Offline customer orders also reserve stock (they are staff-managed and never auto-cancel).
- Completion releases the reservation as it deducts stock; cancellation releases it back to available.
- Unpaid online orders hold their reservation for a configurable window (`online_stores.reservation_hold_minutes`, default 30) and are then auto-cancelled by the scheduled command `sales:expire-reservations`, which releases the held stock. Paid orders are never auto-cancelled.
- **Fallback when the scheduler is not running:** each online checkout first sweeps and releases the tenant's own expired holds (the same `ExpireOnlineOrderReservationsAction`), so abandoned unpaid orders never permanently lock stock even if `schedule:run` is not configured. Admin **Cancel Order** also releases any reservation immediately, independently of the scheduler.

### Deployment requirement

For timely auto-cancellation (rather than only lazy release at the next checkout), the deployment must run Laravel's scheduler — `php artisan schedule:run` every minute via cron/supervisor, e.g.:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The command is registered in `routes/console.php` as `sales:expire-reservations` (every five minutes, without overlapping). No scheduler is configured in the repository yet; add one during deployment.

