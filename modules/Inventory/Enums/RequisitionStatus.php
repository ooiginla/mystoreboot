<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum RequisitionStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Fulfilled => 'Fulfilled',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Approved], true);
    }
}
