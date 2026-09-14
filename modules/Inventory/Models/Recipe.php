<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\ProductVariant;

final class Recipe extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'yield_quantity' => 'decimal:4',
            'is_active' => 'boolean',
            'version' => 'integer',
            'shelf_life_days' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function outputVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'output_product_variant_id');
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'yield_unit_id');
    }

    public function prepStation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'prep_location_id');
    }
}
