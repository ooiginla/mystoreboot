<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class HrPayrollRun extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'posted_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HrPayrollItem::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
