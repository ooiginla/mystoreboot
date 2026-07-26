<?php

declare(strict_types=1);

namespace Modules\Access\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Role extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
            'limits' => 'array',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        return $this->permissions->pluck('slug')->all();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }

    public function limit(string $key): int|float|null
    {
        return $this->limits[$key] ?? null;
    }
}
