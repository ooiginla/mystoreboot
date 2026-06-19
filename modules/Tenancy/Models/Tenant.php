<?php

declare(strict_types=1);

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Department;
use Modules\Tenancy\Enums\TenantStatus;

final class Tenant extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'opening_hours' => 'array',
            'trial_ends_at' => 'datetime',
            'default_tax_rate' => 'decimal:2',
            'status' => TenantStatus::class,
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }
}
