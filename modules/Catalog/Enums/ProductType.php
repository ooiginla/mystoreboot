<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
    case Bundle = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
            self::Bundle => 'Bundle',
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
