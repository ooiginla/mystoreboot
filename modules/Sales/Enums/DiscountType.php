<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum DiscountType: string
{
    case Amount = 'amount';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Amount => 'Amount',
            self::Percentage => 'Percentage',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
