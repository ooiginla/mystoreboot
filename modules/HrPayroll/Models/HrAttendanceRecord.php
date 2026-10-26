<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;

final class HrAttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'hr_staff_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HrShiftAssignment::class, 'hr_shift_assignment_id');
    }
}
