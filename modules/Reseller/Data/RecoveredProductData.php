<?php

declare(strict_types=1);

namespace Modules\Reseller\Data;

final readonly class RecoveredProductData
{
    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public function __construct(
        public string $url,
        public string $name,
        public ?string $imageUrl,
        public int $priceMinor,
        public ?int $previousPriceMinor,
        public string $currencyCode,
        public string $availability,
        public ?string $description,
        public ?string $brand,
        public ?string $category,
        public ?string $sku,
        public array $variants,
        public string $sourceStoreName,
        public ?string $sourceReference,
    ) {}
}
