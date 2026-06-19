<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum SalesPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partially refunded',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
