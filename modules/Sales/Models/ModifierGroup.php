<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\Product;

/**
 * A set of choices offered with a menu item — "Spice level", "Extras", "Remove". One
 * group can be attached to many products, so "Spice level" is set up once and reused.
 */
final class ModifierGroup extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'modifier_group_product')
            ->withPivot(['tenant_id', 'sort_order'])
            ->withTimestamps();
    }

    /** The fewest choices a server must make. A required group needs at least one. */
    public function minimumChoices(): int
    {
        return max((int) $this->min_select, $this->is_required ? 1 : 0);
    }

    /** The rule in plain words, shown above the choices on the pad. */
    public function ruleLabel(): string
    {
        $min = $this->minimumChoices();
        $max = $this->max_select;

        return match (true) {
            $max !== null && $min > 0 && $min === $max => "Pick {$min}",
            $max !== null && $min > 0 => "Pick {$min}–{$max}",
            $min > 0 => "Pick at least {$min}",
            $max !== null => "Optional · up to {$max}",
            default => 'Optional',
        };
    }
}
