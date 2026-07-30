<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\ProductType;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Actions\AdjustInventoryReservationAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Subscriptions\Support\TenantModuleAccess;

final class CompleteSalesOrderAction
{
    public function __construct(
        private readonly PostJournalEntryAction $postJournalEntry,
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly AdjustInventoryReservationAction $reservations,
        private readonly TenantModuleAccess $moduleAccess,
    ) {}

    public function execute(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function () use ($order): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->with([
                    'tenant',
                    'customer',
                    'items.variant.product',
                    'payments.paymentAccount.financeAccount',
                    'payments.tillSession.cashLocation.financeAccount',
                ])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->order_status === SalesOrderStatus::Completed) {
                return $lockedOrder;
            }

            if (! in_array($lockedOrder->order_status, [SalesOrderStatus::Pending, SalesOrderStatus::Processing], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Only pending or processing orders can be completed.',
                ]);
            }

            $inventoryEnabled = $this->moduleAccess->allows($lockedOrder->tenant, 'inventory');
            $location = $inventoryEnabled ? $this->inventoryLocationFor($lockedOrder) : null;
            $useEstimatedCost = (bool) ($lockedOrder->tenant->settings['use_estimated_cost_for_cogs'] ?? false);
            $cogsMinor = 0;

            foreach ($lockedOrder->items as $item) {
                $variant = $item->variant;
                $tracksInventory = $variant?->product?->product_type === ProductType::Product;
                $unitCostMinor = (int) $item->unit_cost_minor;

                if ($inventoryEnabled && $tracksInventory) {
                    $averageCostMinor = (int) InventoryStockLevel::query()
                        ->where('tenant_id', $lockedOrder->tenant_id)
                        ->where('inventory_location_id', $location->id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->value('average_cost_minor');

                    $unitCostMinor = $averageCostMinor > 0
                        ? $averageCostMinor
                        : ($useEstimatedCost ? $this->estimatedUnitCost($item) : 0);

                    // Release the soft reservation first so completing the sale
                    // does not double-count the held stock against availability.
                    if ($lockedOrder->stock_reserved) {
                        $this->reservations->release(
                            $lockedOrder->tenant_id,
                            $location->id,
                            (int) $item->product_variant_id,
                            (int) $item->quantity,
                        );
                    }

                    $this->postInventoryMovement->executeFromSource([
                        'tenant_id' => $lockedOrder->tenant_id,
                        'inventory_location_id' => $location->id,
                        'product_variant_id' => $item->product_variant_id,
                        'movement_type' => InventoryMovementType::StockOut->value,
                        'stock_condition' => StockCondition::Sellable->value,
                        'quantity' => $item->quantity,
                        'unit_cost_minor' => $unitCostMinor,
                        'reference_number' => $lockedOrder->order_number,
                        'notes' => 'Order completed.',
                        'occurred_at' => now(),
                    ], 'sales_order', $lockedOrder->id);
                } elseif (! $inventoryEnabled) {
                    $unitCostMinor = $useEstimatedCost ? $this->estimatedUnitCost($item) : 0;
                }

                if ((int) $item->unit_cost_minor !== $unitCostMinor) {
                    $item->update(['unit_cost_minor' => $unitCostMinor]);
                }

                $cogsMinor += (int) $item->quantity * $unitCostMinor;
            }

            $discountMinor = (int) $lockedOrder->coupon_discount_minor + (int) $lockedOrder->admin_discount_minor;
            $paidMinor = min((int) $lockedOrder->paid_minor, (int) $lockedOrder->total_minor);
            $balanceMinor = max(0, (int) $lockedOrder->total_minor - $paidMinor);
            $gatewayChargeMinor = (int) ($lockedOrder->gateway_charge_minor ?? 0);

            // Payments taken while the order was pending are already recognised as
            // cash/gateway assets offset by Customer Deposits (2310). Completing the
            // sale clears that deposit and books the balance to Accounts Receivable.
            $this->postJournalEntry->execute(
                $lockedOrder->tenant_id,
                now()->toDateString(),
                'Completed sales order '.$lockedOrder->order_number,
                [
                    ['account_code' => '2310', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $paidMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => '1100', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $balanceMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => '4020', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $discountMinor],
                    ['account_code' => '4000', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => (int) $lockedOrder->subtotal_minor],
                    ['account_code' => '2100', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => (int) $lockedOrder->tax_minor],
                    ['account_code' => '4010', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => (int) $lockedOrder->shipping_minor],
                    ['account_code' => '4130', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $gatewayChargeMinor],
                    ['account_code' => 'EXP-5000', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $cogsMinor],
                    ['account_code' => '1200', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $cogsMinor],
                ],
                'sales_order',
                $lockedOrder->id,
                'completed',
            );

            if ($balanceMinor > 0 && $lockedOrder->customer) {
                $lockedOrder->customer->update([
                    'account_balance_minor' => max(0, (int) $lockedOrder->customer->account_balance_minor + $balanceMinor),
                    'last_purchase_at' => now(),
                ]);
            } elseif ($lockedOrder->customer) {
                $lockedOrder->customer->update(['last_purchase_at' => now()]);
            }

            $lockedOrder->update([
                'inventory_location_id' => $location?->id,
                'order_status' => SalesOrderStatus::Completed->value,
                'is_credit_sale' => $balanceMinor > 0,
                'stock_reserved' => false,
                'reserved_until' => null,
            ]);

            return $lockedOrder->refresh()->load(['customer', 'items', 'payments']);
        });
    }

    private function inventoryLocationFor(SalesOrder $order): InventoryLocation
    {
        $location = InventoryLocation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('branch_id', $order->branch_id)
            ->where('status', 'active')
            ->when($order->inventory_location_id, fn ($query) => $query->whereKey($order->inventory_location_id))
            ->first();

        $location ??= InventoryLocation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('branch_id', $order->branch_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'order_status' => 'Set up an active inventory location for this order branch before completing the order.',
            ]);
        }

        return $location;
    }

    private function estimatedUnitCost(mixed $item): int
    {
        return (int) ($item->variant?->cost_price_minor ?: $item->variant?->product?->base_cost_price_minor ?: 0);
    }
}
