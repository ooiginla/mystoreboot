<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Catalog\Models\ProductCategory;

final class OnlineStore extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

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
}
