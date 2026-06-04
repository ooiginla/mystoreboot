<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Department extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
