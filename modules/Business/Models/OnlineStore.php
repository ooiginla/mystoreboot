<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductCollection;

final class OnlineStore extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::created(function (OnlineStore $store): void {
            $category = app(EnsureDefaultProductCategoryAction::class)->execute((string) $store->tenant_id);

            $store->categories()->syncWithoutDetaching([$category->id]);
        });
    }

    protected function casts(): array
    {
        return [
            'payment_methods' => 'array',
            'payment_settings' => 'array',
            'bank_accounts' => 'array',
            'shipping_options' => 'array',
            'social_accounts' => 'array',
            'pages' => 'array',
            'faqs' => 'array',
            'slides' => 'array',
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
        ];
    }

    public function fulfilmentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'fulfilment_branch_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'online_store_categories')->withTimestamps();
    }

    public function productCollections(): HasMany
    {
        return $this->hasMany(ProductCollection::class, 'tenant_id', 'tenant_id');
    }
}
