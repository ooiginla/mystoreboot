<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Draft => 'Draft',
            self::OutOfStock => 'Out of stock',
            self::Discontinued => 'Discontinued',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
