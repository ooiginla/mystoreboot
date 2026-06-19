<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum TaxBehavior: string
{
    case Taxable = 'taxable';
    case Exempt = 'exempt';
    case ZeroRated = 'zero_rated';

    public function label(): string
    {
        return match ($this) {
            self::Taxable => 'Taxable',
            self::Exempt => 'Tax exempt',
            self::ZeroRated => 'Zero-rated',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $behavior): array => [$behavior->value => $behavior->label()])
            ->all();
    }
}
