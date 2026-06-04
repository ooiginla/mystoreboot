<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module_entitlements')
            ->withPivot(['limits', 'is_enabled'])
            ->withTimestamps();
    }
}
