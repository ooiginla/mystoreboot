<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')
            ->select(['id', 'subtotal_minor', 'tax_minor', 'shipping_minor'])
            ->orderBy('id')
            ->chunkById(100, function ($purchaseOrders): void {
                foreach ($purchaseOrders as $purchaseOrder) {
                    $receipts = DB::table('goods_receipts')
                        ->where('purchase_order_id', $purchaseOrder->id)
                        ->orderBy('received_at')
                        ->orderBy('id')
                        ->get();

                    if ($receipts->isEmpty()) {
                        continue;
                    }

                    $orderedQuantity = (int) DB::table('purchase_order_items')
                        ->where('purchase_order_id', $purchaseOrder->id)
                        ->sum('quantity_ordered');
                    $allocatedTaxMinor = 0;
                    $allocatedShippingMinor = 0;
                    $receivedToDate = 0;

                    foreach ($receipts as $receipt) {
                        $receiptItems = DB::table('goods_receipt_items')
                            ->join('purchase_order_items', 'purchase_order_items.id', '=', 'goods_receipt_items.purchase_order_item_id')
                            ->where('goods_receipt_items.goods_receipt_id', $receipt->id)
                            ->select([
                                'goods_receipt_items.quantity_received',
                                'purchase_order_items.unit_cost_minor',
                            ])
                            ->get();
                        $receivedQuantity = (int) $receiptItems->sum('quantity_received');
                        $subtotalMinor = (int) $receiptItems->sum(
                            fn ($item): int => (int) $item->quantity_received * (int) $item->unit_cost_minor,
                        );
                        $receivedToDate += $receivedQuantity;
                        $isFinalReceipt = $receivedToDate >= $orderedQuantity;
                        $taxMinor = $this->allocation(
                            (int) $purchaseOrder->tax_minor,
                            $allocatedTaxMinor,
                            $subtotalMinor,
                            (int) $purchaseOrder->subtotal_minor,
                            $receivedQuantity,
                            $orderedQuantity,
                            $isFinalReceipt,
                        );
                        $shippingMinor = $this->allocation(
                            (int) $purchaseOrder->shipping_minor,
                            $allocatedShippingMinor,
                            $subtotalMinor,
                            (int) $purchaseOrder->subtotal_minor,
                            $receivedQuantity,
                            $orderedQuantity,
                            $isFinalReceipt,
                        );
                        $allocatedTaxMinor += $taxMinor;
                        $allocatedShippingMinor += $shippingMinor;

                        DB::table('goods_receipts')
                            ->where('id', $receipt->id)
                            ->update([
                                'subtotal_minor' => $subtotalMinor,
                                'tax_minor' => $taxMinor,
                                'shipping_minor' => $shippingMinor,
                                'total_minor' => $subtotalMinor + $taxMinor + $shippingMinor,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Accounting allocation history is intentionally retained.
    }

    private function allocation(
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
};
