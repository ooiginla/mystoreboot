<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HrShiftAssignment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'hr_staff_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrWorkShift::class, 'hr_work_shift_id');
    }
}
