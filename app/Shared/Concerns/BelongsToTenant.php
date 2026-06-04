<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Models\Tenant;

trait BelongsToTenant
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
