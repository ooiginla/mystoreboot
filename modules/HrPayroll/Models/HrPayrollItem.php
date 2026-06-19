<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;

final class HrPayrollItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deduction_breakdown' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(HrPayrollRun::class, 'hr_payroll_run_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'hr_staff_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
