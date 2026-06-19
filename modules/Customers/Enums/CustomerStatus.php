<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
