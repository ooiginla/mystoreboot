<?php

declare(strict_types=1);

namespace Modules\Reseller\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Reseller\Enums\PricingMode;

final class ResellerSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = [
        'pricing_mode' => 'percentage',
        'percentage_markup_basis_points' => 1000,
        'fixed_markup_minor' => 0,
        'auto_publish_products' => false,
        'scan_frequency' => 'daily',
        'show_source_store' => true,
        'stale_after_hours' => 36,
        'hide_after_missing_scans' => 3,
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'percentage_markup_basis_points' => 'integer',
            'fixed_markup_minor' => 'integer',
            'auto_publish_products' => 'boolean',
            'show_source_store' => 'boolean',
            'stale_after_hours' => 'integer',
            'hide_after_missing_scans' => 'integer',
        ];
    }
}
