<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum StockCondition: string
{
    case Sellable = 'sellable';
    case Damaged = 'damaged';
    case Returned = 'returned';
    case Expired = 'expired';
    case Quarantined = 'quarantined';

    public function label(): string
    {
        return match ($this) {
            self::Sellable => 'Sellable',
            self::Damaged => 'Damaged',
            self::Returned => 'Returned',
            self::Expired => 'Expired',
            self::Quarantined => 'Quarantined',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $condition): array => [$condition->value => $condition->label()])
            ->all();
    }
}
