<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum SalesOrderStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case PartiallyReturned = 'partially_returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Draft => 'Draft',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
            self::PartiallyReturned => 'Partially returned',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
