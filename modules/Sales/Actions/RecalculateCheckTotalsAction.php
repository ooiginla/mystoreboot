<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;

/**
 * Recomputes a check's money from its live items. Called after every change — adding a
 * round, voiding a line, splitting, merging — so the bill on screen is never stale.
 *
 * Voided lines are excluded from the money but stay on the check as a record: the food
 * was still cooked and its cost was still incurred.
 */
final class RecalculateCheckTotalsAction
{
    public function execute(SalesOrder $check): SalesOrder
    {
        $check->load('items');

        $live = $check->items->filter(fn (SalesOrderItem $item): bool => $item->voided_at === null);

        $subtotalMinor = (int) $live->sum(
            fn (SalesOrderItem $item): int => (int) round((float) $item->quantity * (int) $item->unit_price_minor),
        );
        $taxMinor = (int) $live->sum(fn (SalesOrderItem $item): int => (int) $item->tax_minor);

        // Service charge is computed on the food and drink, never on tax, and never on
        // itself. It is frozen at bill print (6d); until then it tracks the running total.
        $rate = (float) ($check->service_charge_rate ?? 0);
        $serviceChargeMinor = $rate > 0 ? (int) round($subtotalMinor * ($rate / 100)) : 0;

        $check->update([
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'service_charge_minor' => $serviceChargeMinor,
            'total_minor' => $subtotalMinor + $taxMinor + $serviceChargeMinor,
        ]);

        return $check->refresh();
    }
}
