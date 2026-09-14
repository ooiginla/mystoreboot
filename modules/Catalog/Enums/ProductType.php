<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
    case Bundle = 'bundle';
    case RawMaterial = 'raw_material';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
            self::Bundle => 'Bundle',
            self::RawMaterial => 'Raw material',
        };
    }

    /**
     * Types that can be sold (offered on the storefront / POS / catalog). Raw
     * materials are deliberately excluded.
     *
     * @return list<self>
     */
    public static function sellable(): array
    {
        return [self::Product, self::Service, self::Bundle];
    }

    /**
     * Types that hold physical stock (shown in inventory, purchasable, recipe inputs).
     *
     * @return list<self>
     */
    public static function stockable(): array
    {
        return [self::Product, self::RawMaterial];
    }

    /**
     * Selectable types on the sellable-product editor (excludes raw materials, which
     * have their own catalog).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $type): bool => $type === self::RawMaterial)
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
