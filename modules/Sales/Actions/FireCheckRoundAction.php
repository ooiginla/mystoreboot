<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\SaleRecipeDepletionAction;
use Modules\Sales\Enums\TicketStatus;
use Modules\Sales\Models\KitchenOrderTicket;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Support\DineInSettings;
use Modules\Tenancy\Models\Tenant;

/**
 * Sends the unfired items on a check to the kitchen.
 *
 * Two things happen, and the order matters:
 *
 * 1. Items are grouped by the prep station that makes them (`products.prep_station_id`,
 *    already modelled in Phase 0) and one ticket is written per station. The grill sees
 *    the suya, the bar sees the drinks, neither is distracted by the other's work.
 * 2. If the tenant deducts at fire (the default), ingredients leave the store now —
 *    because that is when they are actually consumed. Tenants set to deduct at settle keep
 *    the counter-sale behaviour and nothing is depleted here.
 *
 * Firing is the point of no return: from here, removing an item is a void that must be
 * written off as waste, not a free cancel.
 */
final class FireCheckRoundAction
{
    public function __construct(
        private readonly SaleRecipeDepletionAction $recipeDepletion,
    ) {}

    /**
     * @return array{tickets: Collection<int, KitchenOrderTicket>, failures: array<int, string>}
     *         the tickets written, and any line that could not go with the reason why
     */
    public function execute(SalesOrder $check, Tenant $tenant, bool $allowShort = false): array
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        $check->load(['items.variant.product', 'items.modifiers.componentUnit']);
        $pending = $check->unfiredItems();

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages([
                'check' => 'There is nothing new to send — every item has already gone to the kitchen.',
            ]);
        }

        return DB::transaction(function () use ($check, $tenant, $pending, $allowShort): array {
            $firedAt = now();
            $tickets = collect();
            $failures = [];

            // Deplete first, line by line. A line that cannot be covered stays on the pad
            // with its reason rather than dragging the whole round back — two stockable
            // wines should not be refused because of one peppersoup.
            $sendable = collect();

            foreach ($pending as $item) {
                if (! DineInSettings::depletesAtFire($tenant)) {
                    $sendable->push($item);

                    continue;
                }

                try {
                    $this->depleteLine($check, $item, $allowShort);
                    $sendable->push($item);
                } catch (ValidationException $e) {
                    $failures[(int) $item->id] = $item->item_name.': '.collect($e->errors())->flatten()->first();
                }
            }

            if ($sendable->isEmpty()) {
                // Nothing could go; surface it as an error rather than a silent success.
                throw ValidationException::withMessages(['check' => array_values($failures)]);
            }

            // One ticket per station per course, so a station is never handed two courses
            // on one card and cannot tell them apart.
            $grouped = $sendable->groupBy(fn (SalesOrderItem $item): string => sprintf(
                '%s:%s',
                $item->variant?->product?->prep_location_id ?? 'none',
                $item->course ?? 1,
            ));

            foreach ($grouped as $key => $items) {
                [$stationKey, $course] = explode(':', (string) $key);

                $ticket = KitchenOrderTicket::query()->create([
                    'tenant_id' => $check->tenant_id,
                    'sales_order_id' => $check->id,
                    'prep_location_id' => $stationKey === 'none' ? null : (int) $stationKey,
                    'ticket_number' => $this->nextTicketNumber($check->tenant_id),
                    'course' => (int) $course,
                    'status' => TicketStatus::Queued->value,
                    'fired_at' => $firedAt,
                ]);

                foreach ($items as $item) {
                    $ticket->items()->create([
                        'tenant_id' => $check->tenant_id,
                        'kitchen_order_ticket_id' => $ticket->id,
                        'sales_order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'status' => TicketStatus::Queued->value,
                    ]);

                    $item->update(['fired_at' => $firedAt]);
                }

                $tickets->push($ticket);
            }

            return ['tickets' => $tickets, 'failures' => $failures];
        });
    }

    /**
     * Consume one fired line's recipe, adjusted by its modifiers ("no onions", "extra
     * chicken"). A bottled drink is stock-tracked and leaves its shelf at settle, but a
     * modifier on it that uses an ingredient ("extra shot") is consumed now.
     *
     * The line remembers that it has consumed, and at what cost, so settling books that
     * cost instead of depleting a second time.
     *
     * Throws ValidationException when the station cannot cover it and the caller has not
     * accepted going short.
     */
    private function depleteLine(SalesOrder $check, SalesOrderItem $item, bool $allowShort): void
    {
        $variant = $item->variant;
        $adjustments = $item->ingredientAdjustments();
        $usesRecipe = $variant instanceof ProductVariant && ($variant->product?->usesRecipeDepletion() ?? false);

        if (! $variant instanceof ProductVariant || (! $usesRecipe && $adjustments === [])) {
            return;
        }

        // The line already carries the store it belongs to, resolved when it was added:
        // the station that makes it, else the check's own store.
        $locationId = (int) ($item->inventory_location_id ?: $check->inventory_location_id);

        if ($locationId <= 0) {
            throw ValidationException::withMessages([
                'items' => 'no kitchen is set for this item, so there is nowhere to take the ingredients from.',
            ]);
        }

        $costMinor = $this->recipeDepletion->deplete(
            $check->tenant_id,
            $locationId,
            $variant,
            (float) $item->quantity,
            $check->order_number,
            $check->id,
            $allowShort,
            $adjustments,
        );

        $item->update([
            'ingredients_depleted_at' => now(),
            'consumed_cost_minor' => $costMinor,
        ]);
    }

    private function nextTicketNumber(string $tenantId): string
    {
        $prefix = 'KOT-'.now()->format('Ymd').'-';
        $seq = KitchenOrderTicket::query()->where('tenant_id', $tenantId)->where('ticket_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (KitchenOrderTicket::query()->where('tenant_id', $tenantId)->where('ticket_number', $candidate)->exists());

        return $candidate;
    }
}
