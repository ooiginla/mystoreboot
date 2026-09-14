<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Actions\SaleRecipeDepletionAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Enums\TicketStatus;
use Modules\Sales\Models\KitchenOrderTicketItem;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Support\CheckLines;

/**
 * Voids food or drink that has already gone to the kitchen.
 *
 * The rule follows the food, not the paperwork: it was made, so its cost was incurred.
 * Stock is never quietly put back. The line comes off the bill, and what it consumed is
 * written off to Inventory Shrinkage and Write-Offs with the reason, where stock-take
 * variance also lands — so waste is measurable, not hidden.
 *
 * Part of a line can be voided ("one of the three beers was flat"); the rest stays.
 */
final class VoidCheckItemAction
{
    public function __construct(
        private readonly SaleRecipeDepletionAction $recipeDepletion,
        private readonly PostInventoryMovementAction $movements,
        private readonly PostJournalEntryAction $journal,
        private readonly RecalculateCheckTotalsAction $totals,
    ) {}

    public function execute(SalesOrderItem $item, User $by, string $reason, ?float $quantity = null): SalesOrderItem
    {
        $check = SalesOrder::query()->with('tenant')->findOrFail($item->sales_order_id);

        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        if ($item->voided_at !== null) {
            throw ValidationException::withMessages(['items' => "{$item->item_name} has already been voided."]);
        }

        if (! $item->isFired()) {
            throw ValidationException::withMessages([
                'items' => "{$item->item_name} has not gone to the kitchen yet — remove it instead of voiding it.",
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for the void.']);
        }

        $lineQuantity = (float) $item->quantity;
        $quantity = $quantity === null ? $lineQuantity : Quantity::round($quantity);

        if ($quantity <= 0 || $quantity > $lineQuantity + 0.00001) {
            throw ValidationException::withMessages([
                'quantity' => 'Void between 1 and '.Quantity::format($lineQuantity).' of '.$item->item_name.'.',
            ]);
        }

        return DB::transaction(function () use ($check, $item, $by, $reason, $quantity, $lineQuantity): SalesOrderItem {
            $whole = $quantity >= $lineQuantity - 0.00001;
            $target = $whole ? $item : CheckLines::splitLine($item, $quantity);

            $wasteMinor = $this->writeOff($check, $target, $reason);

            $target->update([
                'voided_at' => now(),
                'void_reason' => Str::limit($reason, 160, ''),
                'voided_by_user_id' => $by->id,
                'unit_cost_minor' => (int) round($wasteMinor / max(0.0001, (float) $target->quantity)),
            ]);

            $this->tellTheKitchen($item, $quantity, $whole);

            $check->reopenIfBilled();
            $this->totals->execute($check);

            return $target->refresh();
        });
    }

    /**
     * Take what the line consumed out of the books as waste, and out of the store if it
     * had not left yet. Returns the cost written off.
     */
    private function writeOff(SalesOrder $check, SalesOrderItem $item, string $reason): int
    {
        $variant = $item->variant()->with('product')->first();
        $locationId = (int) ($item->inventory_location_id ?: $check->inventory_location_id);
        $quantity = (float) $item->quantity;
        $adjustments = $item->ingredientAdjustments();
        $costMinor = 0;

        if ($item->ingredients_depleted_at !== null) {
            // Already off the shelf when it was sent; only the books still count it as stock.
            $costMinor += (int) $item->consumed_cost_minor;
        } elseif ($variant && $locationId > 0 && (($variant->product?->usesRecipeDepletion() ?? false) || $adjustments !== [])) {
            // This business deducts at payment, but the dish was cooked — the ingredients go now.
            $costMinor += $this->recipeDepletion->deplete(
                $check->tenant_id,
                $locationId,
                $variant,
                $quantity,
                $check->order_number,
                $check->id,
                true,
                $adjustments,
            );
        }

        if ($variant && $locationId > 0 && CheckLines::isStockTracked($variant)) {
            // A bottle opened or a plate served is counted in its own right; it leaves now.
            $averageCostMinor = (int) InventoryStockLevel::query()
                ->where('tenant_id', $check->tenant_id)
                ->where('inventory_location_id', $locationId)
                ->where('product_variant_id', $variant->id)
                ->value('average_cost_minor');

            $this->movements->executeFromSource([
                'tenant_id' => $check->tenant_id,
                'inventory_location_id' => $locationId,
                'product_variant_id' => $variant->id,
                'movement_type' => InventoryMovementType::StockOut->value,
                'stock_condition' => StockCondition::Sellable->value,
                'quantity' => $quantity,
                'unit_cost_minor' => $averageCostMinor,
                'reference_number' => $check->order_number,
                'notes' => 'Voided after it was sent: '.$reason,
                'occurred_at' => now(),
                'allow_negative' => true,
            ], 'sales_order', $check->id);

            $costMinor += (int) round($quantity * $averageCostMinor);
        }

        if ($costMinor > 0) {
            $this->journal->execute(
                $check->tenant_id,
                now()->toDateString(),
                "Void (waste) {$check->order_number}: ".Quantity::format($quantity)." × {$item->item_name} — {$reason}",
                [
                    ['account_code' => 'EXP-6050', 'branch_id' => $check->branch_id, 'debit_minor' => $costMinor],
                    ['account_code' => '1200', 'branch_id' => $check->branch_id, 'credit_minor' => $costMinor],
                ],
                'sales_order_item',
                $item->id,
                'voided',
            );
        }

        return $costMinor;
    }

    /**
     * The kitchen must stop cooking what is no longer wanted. A whole line is struck off
     * its ticket; a partial void lowers the count on it.
     */
    private function tellTheKitchen(SalesOrderItem $original, float $quantity, bool $whole): void
    {
        $lines = KitchenOrderTicketItem::query()
            ->with('ticket')
            ->where('sales_order_item_id', $original->id)
            ->get();

        foreach ($lines as $line) {
            $line->update($whole
                ? ['status' => TicketStatus::Cancelled->value]
                : ['quantity' => Quantity::round(max(0, (float) $line->quantity - $quantity))]);

            $ticket = $line->ticket;

            if ($ticket && $ticket->items()->where('status', '!=', TicketStatus::Cancelled->value)->doesntExist()) {
                $ticket->update(['status' => TicketStatus::Cancelled->value]);
            }
        }
    }
}
