<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

/**
 * The physical dimension a unit of measure belongs to. Conversions are only ever
 * valid within the same dimension (you can turn kg into g, never kg into litres).
 */
enum UnitDimension: string
{
    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';

    public function label(): string
    {
        return match ($this) {
            self::Count => 'Count',
            self::Weight => 'Weight',
            self::Volume => 'Volume',
            self::Length => 'Length',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $dimension): array => [$dimension->value => $dimension->label()])
            ->all();
    }
}
