<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

/**
 * How a product's stock behaves at the point of sale.
 *
 * - Tracked: deducts its own on-hand stock (ordinary goods, and produced finished
 *   goods replenished by production).
 * - Recipe: holds no own stock; selling it deducts its recipe's ingredients directly
 *   (à la carte, cooked to order).
 * - Untracked: no depletion at all (services, made-to-order).
 */
enum StockPolicy: string
{
    case Tracked = 'tracked';
    case Recipe = 'recipe';
    case Untracked = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Tracked => 'Track own stock',
            self::Recipe => 'Deplete recipe ingredients on sale',
            self::Untracked => 'No stock tracking',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $policy): array => [$policy->value => $policy->label()])
            ->all();
    }
}
