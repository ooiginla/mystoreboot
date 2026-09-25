<?php

declare(strict_types=1);

namespace Modules\Reseller\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reseller\Enums\ProductAvailability;

final class ResellerProduct extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = [
        'availability' => 'unknown',
        'is_visible' => false,
        'is_excluded' => false,
        'missing_scan_count' => 0,
    ];

    protected static function booted(): void
    {
        self::saving(function (ResellerProduct $product): void {
            $product->product_url_hash = hash('sha256', rtrim(strtolower((string) $product->product_url), '/'));
        });
    }

    protected function casts(): array
    {
        return [
            'source_price_minor' => 'integer',
            'source_previous_price_minor' => 'integer',
            'selling_price_minor' => 'integer',
            'selling_previous_price_minor' => 'integer',
            'availability' => ProductAvailability::class,
            'variants' => 'array',
            'is_visible' => 'boolean',
            'is_excluded' => 'boolean',
            'last_checked_at' => 'datetime',
            'missing_scan_count' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ResellerSupplier::class, 'supplier_id');
    }
}
