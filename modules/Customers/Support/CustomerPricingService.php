<?php

declare(strict_types=1);

namespace Modules\Customers\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerPriceList;
use Modules\Customers\Models\CustomerPriceListItem;
use Modules\Customers\Models\CustomerSpecificPrice;
use Modules\Tenancy\Models\Tenant;

final class CustomerPricingService
{
    /**
     * Resolve authoritative unit prices for a customer's cart.
     *
     * @param  array<int, float|int|string>  $variantQuantities  variant id => aggregate quantity
     * @return Collection<int, array{variant_id:int, base_unit_price_minor:int, unit_price_minor:int, source:string, label:string, reference_id:int|null, minimum_quantity:float, allow_additional_discounts:bool}>
     */
    public function quotes(Tenant $tenant, Customer $customer, array $variantQuantities): Collection
    {
        $quantities = collect($variantQuantities)
            ->mapWithKeys(fn ($quantity, $variantId): array => [(int) $variantId => max(0.0001, (float) $quantity)])
            ->filter(fn (float $quantity, int $variantId): bool => $variantId > 0 && $quantity > 0);

        if ($quantities->isEmpty()) {
            return collect();
        }

        $customer->loadMissing(['group.priceList', 'priceList']);
        $today = CarbonImmutable::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
        $variantIds = $quantities->keys()->all();
        $variants = ProductVariant::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $specificPrices = CustomerSpecificPrice::query()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->whereIn('product_variant_id', $variantIds)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today->toDateString()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today->toDateString()))
            ->get()
            ->groupBy('product_variant_id');

        $directList = $this->activeList($customer->priceList, $today);
        $groupList = $this->activeList($customer->group?->priceList, $today);
        $listIds = collect([$directList?->id, $groupList?->id])->filter()->unique()->values();
        $listItems = $listIds->isEmpty()
            ? collect()
            : CustomerPriceListItem::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('customer_price_list_id', $listIds)
                ->whereIn('product_variant_id', $variantIds)
                ->get()
                ->groupBy(fn (CustomerPriceListItem $item): string => $item->customer_price_list_id.':'.$item->product_variant_id);

        return $quantities->map(function (float $quantity, int $variantId) use ($variants, $specificPrices, $directList, $groupList, $listItems): array {
            /** @var ProductVariant $variant */
            $variant = $variants->get($variantId);
            abort_unless($variant, 422, 'A selected product is not available for this business.');
            $basePrice = (int) $variant->selling_price_minor;

            $specific = $this->bestTier($specificPrices->get($variantId, collect()), $quantity);
            if ($specific instanceof CustomerSpecificPrice) {
                return $this->quote(
                    $variantId,
                    $basePrice,
                    (int) $specific->unit_price_minor,
                    'customer_price',
                    'Customer price',
                    $specific->id,
                    (float) $specific->minimum_quantity,
                    (bool) $specific->allow_additional_discounts,
                );
            }

            foreach ([['list' => $directList, 'source' => 'customer_price_list'], ['list' => $groupList, 'source' => 'group_price_list']] as $candidate) {
                /** @var CustomerPriceList|null $list */
                $list = $candidate['list'];
                if (! $list) {
                    continue;
                }

                $tier = $this->bestTier($listItems->get($list->id.':'.$variantId, collect()), $quantity);
                if ($tier instanceof CustomerPriceListItem) {
                    $unitPrice = $tier->price_type === 'percentage'
                        ? (int) round($basePrice * (1 - ((float) $tier->discount_percent / 100)))
                        : (int) $tier->unit_price_minor;

                    return $this->quote(
                        $variantId,
                        $basePrice,
                        max(0, $unitPrice),
                        $candidate['source'],
                        $list->name,
                        $tier->id,
                        (float) $tier->minimum_quantity,
                        (bool) $list->allow_additional_discounts,
                    );
                }
            }

            return $this->quote($variantId, $basePrice, $basePrice, 'retail', 'Retail', null, 1, true);
        });
    }

    private function activeList(?CustomerPriceList $list, CarbonImmutable $today): ?CustomerPriceList
    {
        $todayDate = $today->toDateString();

        if (! $list?->is_active
            || ($list->starts_at && $list->starts_at->toDateString() > $todayDate)
            || ($list->ends_at && $list->ends_at->toDateString() < $todayDate)) {
            return null;
        }

        return $list;
    }

    /** @param Collection<int, CustomerSpecificPrice|CustomerPriceListItem> $tiers */
    private function bestTier(Collection $tiers, float $quantity): CustomerSpecificPrice|CustomerPriceListItem|null
    {
        return $tiers
            ->filter(fn ($tier): bool => (float) $tier->minimum_quantity <= $quantity + 0.00001)
            ->sortByDesc(fn ($tier): float => (float) $tier->minimum_quantity)
            ->first();
    }

    /** @return array{variant_id:int, base_unit_price_minor:int, unit_price_minor:int, source:string, label:string, reference_id:int|null, minimum_quantity:float, allow_additional_discounts:bool} */
    private function quote(
        int $variantId,
        int $basePriceMinor,
        int $unitPriceMinor,
        string $source,
        string $label,
        ?int $referenceId,
        float $minimumQuantity,
        bool $allowAdditionalDiscounts,
    ): array {
        return [
            'variant_id' => $variantId,
            'base_unit_price_minor' => $basePriceMinor,
            'unit_price_minor' => $unitPriceMinor,
            'source' => $source,
            'label' => $label,
            'reference_id' => $referenceId,
            'minimum_quantity' => $minimumQuantity,
            'allow_additional_discounts' => $allowAdditionalDiscounts,
        ];
    }
}
