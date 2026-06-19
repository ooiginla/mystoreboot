<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;

final class HrStaff extends Model
{
    use BelongsToTenant;

    protected $table = 'hr_staff';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(HrStaffDeduction::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(HrPayrollItem::class);
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
