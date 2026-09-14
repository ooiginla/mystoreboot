<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

/**
 * Lifecycle of a restaurant check. Null on a sales order means it is an ordinary retail
 * sale, not a dine-in check.
 */
enum CheckStatus: string
{
    case Open = 'open';
    case BillPrinted = 'bill_printed';
    case Settled = 'settled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::BillPrinted => 'Bill printed',
            self::Settled => 'Settled',
            self::Voided => 'Voided',
        };
    }

    /** Items can still be added and fired. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::BillPrinted], true);
    }
}
