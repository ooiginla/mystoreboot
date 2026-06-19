<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum CategoryType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product category',
            self::Service => 'Service category',
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
