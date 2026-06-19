<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum InventoryLocationType: string
{
    case Branch = 'branch';
    case Warehouse = 'warehouse';
    case StoreRoom = 'store_room';
    case ServiceUnit = 'service_unit';

    public function label(): string
    {
        return match ($this) {
            self::Branch => 'Branch',
            self::Warehouse => 'Warehouse',
            self::StoreRoom => 'Store room',
            self::ServiceUnit => 'Service unit',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
