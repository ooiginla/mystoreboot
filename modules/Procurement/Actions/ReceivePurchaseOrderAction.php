<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Finance\Models\FinanceJournalLine;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;

final class ReceivePurchaseOrderAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly PostJournalEntryAction $postJournalEntry,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PurchaseOrder $purchaseOrder, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $data): GoodsReceipt {
            $purchaseOrder->loadMissing(['items.location']);

            $validItems = collect((array) $data['items'])
                ->map(function (array $item) use ($purchaseOrder): array {
                    $quantity = (int) ($item['quantity_received'] ?? 0);
                    $poItem = $purchaseOrder->items()->whereKey($item['purchase_order_item_id'])->firstOrFail();

                    if ($quantity > $poItem->quantity_pending) {
                        throw ValidationException::withMessages([
                            'items' => 'Received quantity cannot be more than the pending quantity.',
                        ]);
                    }

                    return [$item, $poItem, $quantity];
                })
                ->filter(fn (array $row): bool => $row[2] > 0)
                ->values();

            if ($validItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter at least one quantity to receive.',
                ]);
            }

            $receiptSubtotalMinor = (int) $validItems->sum(
                fn (array $row): int => $row[1]->unit_cost_minor * $row[2],
            );
            $receivedQuantity = (int) $validItems->sum(fn (array $row): int => $row[2]);
            $pendingQuantity = (int) $purchaseOrder->items->sum(fn ($item): int => $item->quantity_pending);
            $isFinalReceipt = $receivedQuantity === $pendingQuantity;
            $previousTaxMinor = (int) $purchaseOrder->receipts()->sum('tax_minor');
            $previousShippingMinor = (int) $purchaseOrder->receipts()->sum('shipping_minor');
            $receiptTaxMinor = $this->receiptAllocation(
                (int) $purchaseOrder->tax_minor,
                $previousTaxMinor,
                $receiptSubtotalMinor,
                (int) $purchaseOrder->subtotal_minor,
                $receivedQuantity,
                (int) $purchaseOrder->items->sum('quantity_ordered'),
                $isFinalReceipt,
            );
            $receiptShippingMinor = $this->receiptAllocation(
                (int) $purchaseOrder->shipping_minor,
                $previousShippingMinor,
                $receiptSubtotalMinor,
                (int) $purchaseOrder->subtotal_minor,
                $receivedQuantity,
                (int) $purchaseOrder->items->sum('quantity_ordered'),
                $isFinalReceipt,
            );
            $weights = $validItems
                ->map(fn (array $row): int => ($row[1]->unit_cost_minor * $row[2]) ?: $row[2])
                ->all();
            $shippingByItem = $this->distributeAmount($receiptShippingMinor, $weights);
            $taxByItem = $this->distributeAmount($receiptTaxMinor, $weights);

            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_number' => $data['receipt_number'] ?: $this->generateReceiptNumber($purchaseOrder),
                'received_at' => $data['received_at'],
                'delivery_status' => 'received',
                'subtotal_minor' => $receiptSubtotalMinor,
                'tax_minor' => $receiptTaxMinor,
                'shipping_minor' => $receiptShippingMinor,
                'total_minor' => $receiptSubtotalMinor + $receiptTaxMinor + $receiptShippingMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $accountingRows = [];

            foreach ($validItems as $index => [$item, $poItem, $quantity]) {
                $receipt->items()->create([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'purchase_order_item_id' => $poItem->id,
                    'quantity_received' => $quantity,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                $poItem->increment('quantity_received', $quantity);
                $inventoryValueMinor = ($poItem->unit_cost_minor * $quantity) + $shippingByItem[$index];
                $landedUnitCostMinor = (int) round($inventoryValueMinor / $quantity);
                $branchId = $poItem->location?->branch_id;

                $this->postInventoryMovement->executeFromSource([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'inventory_location_id' => $poItem->inventory_location_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'movement_type' => InventoryMovementType::StockIn->value,
                    'stock_condition' => StockCondition::Sellable->value,
                    'quantity' => $quantity,
                    'unit_cost_minor' => $landedUnitCostMinor,
                    'movement_value_minor' => $inventoryValueMinor,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'reference_number' => $purchaseOrder->po_number,
                    'notes' => 'Goods received from supplier.',
                    'occurred_at' => $data['received_at'],
                ], 'goods_receipt', $receipt->id);

                $accountingRows[] = [
                    'branch_id' => $branchId,
                    'inventory_minor' => $inventoryValueMinor,
                    'tax_minor' => $taxByItem[$index],
                    'total_minor' => $inventoryValueMinor + $taxByItem[$index],
                ];
            }

            $purchaseOrder->refresh()->load('items');
            $allReceived = $purchaseOrder->items->every(fn ($item): bool => $item->quantity_received >= $item->quantity_ordered);
            $anyReceived = $purchaseOrder->items->contains(fn ($item): bool => $item->quantity_received > 0);

            $purchaseOrder->update([
                'status' => $allReceived
                    ? PurchaseOrderStatus::Received->value
                    : ($anyReceived ? PurchaseOrderStatus::PartiallyReceived->value : $purchaseOrder->status->value),
            ]);

            if ($this->hasLegacyApprovalJournal($purchaseOrder)) {
                $this->postLegacyLandedCostAllocation($purchaseOrder, $receipt, $accountingRows);
            } else {
                $this->postReceiptAccounting($purchaseOrder, $receipt, $accountingRows);
            }

            return $receipt->refresh()->load(['items.purchaseOrderItem.variant.product']);
        });
    }

    /**
     * @param  array<int, array{branch_id: ?int, inventory_minor: int, tax_minor: int, total_minor: int}>  $rows
     */
    private function postReceiptAccounting(PurchaseOrder $purchaseOrder, GoodsReceipt $receipt, array $rows): void
    {
        $advanceAppliedMinor = min(
            (int) $receipt->total_minor,
            $this->availableVendorAdvanceMinor($purchaseOrder),
        );
        $advanceByItem = $this->distributeAmount(
            $advanceAppliedMinor,
            array_column($rows, 'total_minor'),
        );
        $lines = [];

        foreach ($rows as $index => $row) {
            $advanceMinor = $advanceByItem[$index];
            $payableMinor = $row['total_minor'] - $advanceMinor;
            $lines[] = ['account_code' => '1200', 'branch_id' => $row['branch_id'], 'debit_minor' => $row['inventory_minor']];
            $lines[] = ['account_code' => '1320', 'branch_id' => $row['branch_id'], 'debit_minor' => $row['tax_minor']];
            $lines[] = ['account_code' => '1220', 'branch_id' => $row['branch_id'], 'credit_minor' => $advanceMinor, 'party_type' => 'vendor', 'party_id' => $purchaseOrder->vendor_id];
            $lines[] = ['account_code' => '2000', 'branch_id' => $row['branch_id'], 'credit_minor' => $payableMinor, 'party_type' => 'vendor', 'party_id' => $purchaseOrder->vendor_id];
        }

        $this->postJournalEntry->execute(
            $purchaseOrder->tenant_id,
            $receipt->received_at->toDateString(),
            'Goods receipt '.$receipt->receipt_number,
            $lines,
            'goods_receipt',
            $receipt->id,
            'received',
        );
    }

    /**
     * Existing approved purchase orders already debited merchandise and freight.
     * Their receipts only need to move allocated freight from clearing into Inventory.
     *
     * @param  array<int, array{branch_id: ?int, inventory_minor: int, tax_minor: int, total_minor: int}>  $rows
     */
    private function postLegacyLandedCostAllocation(PurchaseOrder $purchaseOrder, GoodsReceipt $receipt, array $rows): void
    {
        if ($receipt->shipping_minor <= 0) {
            return;
        }

        $shippingByItem = $this->distributeAmount(
            (int) $receipt->shipping_minor,
            array_column($rows, 'inventory_minor'),
        );
        $lines = [];

        foreach ($rows as $index => $row) {
            $lines[] = ['account_code' => '1200', 'branch_id' => $row['branch_id'], 'debit_minor' => $shippingByItem[$index]];
            $lines[] = ['account_code' => '1210', 'branch_id' => $row['branch_id'], 'credit_minor' => $shippingByItem[$index]];
        }

        $this->postJournalEntry->execute(
            $purchaseOrder->tenant_id,
            $receipt->received_at->toDateString(),
            'Landed cost allocation '.$receipt->receipt_number,
            $lines,
            'goods_receipt',
            $receipt->id,
            'landed_cost_allocated',
        );
    }

    private function hasLegacyApprovalJournal(PurchaseOrder $purchaseOrder): bool
    {
        return FinanceJournalEntry::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('source_type', 'purchase_order')
            ->where('source_id', $purchaseOrder->id)
            ->where('source_event', 'approved')
            ->exists();
    }

    private function availableVendorAdvanceMinor(PurchaseOrder $purchaseOrder): int
    {
        $paymentIds = $purchaseOrder->payments()->pluck('id');
        $receiptIds = $purchaseOrder->receipts()->pluck('id');

        $lines = FinanceJournalLine::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->whereHas('account', fn ($query) => $query->where('code', '1220'))
            ->whereHas('entry', fn ($query) => $query->where(function ($sourceQuery) use ($paymentIds, $receiptIds): void {
                $sourceQuery
                    ->where(fn ($paymentQuery) => $paymentQuery
                        ->where('source_type', 'vendor_payment')
                        ->whereIn('source_id', $paymentIds))
                    ->orWhere(fn ($receiptQuery) => $receiptQuery
                        ->where('source_type', 'goods_receipt')
                        ->whereIn('source_id', $receiptIds));
            }))
            ->get();

        return max(0, (int) $lines->sum('debit_minor') - (int) $lines->sum('credit_minor'));
    }

    private function receiptAllocation(
        int $totalMinor,
        int $previouslyAllocatedMinor,
        int $receiptSubtotalMinor,
        int $purchaseSubtotalMinor,
        int $receivedQuantity,
        int $orderedQuantity,
        bool $isFinalReceipt,
    ): int {
        $remainingMinor = max(0, $totalMinor - $previouslyAllocatedMinor);

        if ($remainingMinor === 0 || $isFinalReceipt) {
            return $remainingMinor;
        }

        $allocation = $purchaseSubtotalMinor > 0
            ? (int) round($totalMinor * ($receiptSubtotalMinor / $purchaseSubtotalMinor))
            : (int) round($totalMinor * ($receivedQuantity / max(1, $orderedQuantity)));

        return min($remainingMinor, max(0, $allocation));
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function distributeAmount(int $amountMinor, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);
        $remaining = $amountMinor;
        $lastIndex = array_key_last($weights);
        $allocations = [];

        foreach ($weights as $index => $weight) {
            $allocation = $index === $lastIndex
                ? $remaining
                : (int) round($amountMinor * ($weight / max(1, $totalWeight)));
            $allocation = min($remaining, max(0, $allocation));
            $allocations[$index] = $allocation;
            $remaining -= $allocation;
        }

        return $allocations;
    }

    private function generateReceiptNumber(PurchaseOrder $purchaseOrder): string
    {
        return 'GRN-'.now()->format('Ymd').'-'.$purchaseOrder->id.'-'.($purchaseOrder->receipts()->count() + 1);
    }
}
