<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum StockCountStatus: string
{
    case Counting = 'counting';
    case Review = 'review';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Counting => 'Counting',
            self::Review => 'In review',
            self::Posted => 'Posted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Counting, self::Review], true);
    }
}
