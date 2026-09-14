<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\RestaurantTable;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\DineInSettings;
use Modules\Tenancy\Models\Tenant;

/**
 * Seats guests at a table and opens the check they will build up over the meal.
 *
 * The check is a sales_order with a service area attached — everything downstream
 * (payments, receipts, COGS, GL) is the machinery that already exists.
 */
final class OpenCheckAction
{
    public function execute(
        string $tenantId,
        RestaurantTable $table,
        int $coverCount,
        ?User $server = null,
    ): SalesOrder
    {
        return DB::transaction(function () use ($tenantId, $table, $coverCount, $server): SalesOrder {
            $table->loadMissing('serviceArea.sellableLocation');
            $area = $table->serviceArea;

            if ($area === null || $area->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'That table does not belong to this business.',
                ]);
            }

            if ($table->openCheck() !== null) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => "Table {$table->name} already has an open check.",
                ]);
            }

            // Without a store, every line on this check would have nowhere to deplete
            // from. Better to refuse now than to discover it at fire time.
            if ($area->stockLocationId() === null) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => "{$area->name} has no store set. Open the floor plan and choose where this area sells from.",
                ]);
            }

            if ($coverCount < 1) {
                throw ValidationException::withMessages([
                    'cover_count' => 'Enter how many guests are seated.',
                ]);
            }

            $check = SalesOrder::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $area->branch_id,
                'service_area_id' => $area->id,
                'restaurant_table_id' => $table->id,
                'server_user_id' => $server?->id,
                'cover_count' => $coverCount,
                'opened_at' => now(),
                'check_status' => CheckStatus::Open->value,
                // Stock comes from the sales point behind the area (Phase 0 chain).
                'inventory_location_id' => $area->stockLocationId(),
                'order_number' => $this->nextNumber($tenantId),
                // Required on every sales order; a check settles like any other sale.
                'invoice_number' => $this->number('INV', $tenantId),
                'receipt_number' => $this->number('RCT', $tenantId),
                'order_status' => SalesOrderStatus::Pending->value,
                'payment_status' => SalesPaymentStatus::Unpaid->value,
                'order_date' => now()->toDateString(),
                'source' => 'restaurant',
                'user_id' => $server?->id,
                'subtotal_minor' => 0,
                'tax_minor' => 0,
                'service_charge_minor' => 0,
                // Snapshot the rate now: changing it in settings later must not re-price
                // a bill a table has already been running up.
                'service_charge_rate' => DineInSettings::serviceChargeRate(Tenant::query()->findOrFail($tenantId)),
                'total_minor' => 0,
                'paid_minor' => 0,
            ]);

            $table->update(['status' => TableStatus::Occupied->value]);

            return $check;
        });
    }

    private function number(string $prefix, string $tenantId): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad(
            (string) (SalesOrder::query()->where('tenant_id', $tenantId)->count() + 1),
            5,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function nextNumber(string $tenantId): string
    {
        $prefix = 'CHK-'.now()->format('Ymd').'-';
        $seq = SalesOrder::query()->where('tenant_id', $tenantId)->where('order_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (SalesOrder::query()->where('tenant_id', $tenantId)->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
