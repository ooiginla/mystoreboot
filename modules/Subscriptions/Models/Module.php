<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Module extends Model
{
    protected $guarded = [];

    protected $table = 'billable_modules';

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenantEntitlements(): HasMany
    {
        return $this->hasMany(TenantModuleEntitlement::class);
    }
}
