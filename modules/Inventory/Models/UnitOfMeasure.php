<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Enums\UnitDimension;

final class UnitOfMeasure extends Model
{
    use BelongsToTenant;

    protected $table = 'units_of_measure';

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    protected function casts(): array
    {
        return [
            'dimension' => UnitDimension::class,
            'to_base_factor' => 'decimal:6',
            'is_base_for_dimension' => 'boolean',
        ];
    }

    public function isConvertible(): bool
    {
        return $this->to_base_factor !== null;
    }
}
