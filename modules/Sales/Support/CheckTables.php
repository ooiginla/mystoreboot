<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\RestaurantTable;
use Modules\Sales\Models\SalesOrder;

final class CheckTables
{
    /**
     * Free a table once nothing is left open on it. A split leaves sibling checks on the
     * same table, so settling one of them must not mark the table free while the other
     * guests are still eating.
     */
    public static function releaseIfClear(?RestaurantTable $table, TableStatus $status): void
    {
        if ($table === null) {
            return;
        }

        $stillOpen = SalesOrder::query()
            ->where('tenant_id', $table->tenant_id)
            ->where('restaurant_table_id', $table->id)
            ->whereIn('check_status', ['open', 'bill_printed'])
            ->exists();

        if (! $stillOpen) {
            $table->update(['status' => $status->value]);
        }
    }
}
