<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;

final class HrWorkShift extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HrShiftAssignment::class);
    }
}
