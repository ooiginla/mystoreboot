<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UnitCategory extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(UnitOfMeasure::class)->orderByDesc('is_base_for_dimension')->orderBy('to_base_factor');
    }
}
