<?php

declare(strict_types=1);

namespace Modules\Procurement\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }
}
