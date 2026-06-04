<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Branch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'settings' => 'array',
            'opening_hours' => 'array',
            'default_tax_rate' => 'decimal:2',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
