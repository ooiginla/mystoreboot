<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerGroup extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
