<?php

declare(strict_types=1);

namespace Modules\Reseller\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reseller\Enums\ScanStatus;

final class ResellerSupplier extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
        'last_scan_status' => 'never_run',
    ];

    protected static function booted(): void
    {
        self::saving(function (ResellerSupplier $supplier): void {
            $supplier->website_url_hash = hash('sha256', rtrim(strtolower((string) $supplier->website_url), '/'));
        });
    }

    protected function casts(): array
    {
        return [
            'scan_configuration' => 'array',
            'auto_publish_products' => 'boolean',
            'is_active' => 'boolean',
            'last_scanned_at' => 'datetime',
            'last_scan_status' => ScanStatus::class,
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(ResellerProduct::class, 'supplier_id');
    }
}
