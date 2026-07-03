<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum SalesPaymentStatus: string
{
    case Pending = 'pending';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case CustomerCredit = 'customer_credit';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::CustomerCredit => 'Customer credit',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partially refunded',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
