<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;

final class ProductionWorkOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['production_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'production_station_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrWorkShift::class, 'hr_work_shift_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'production_lead_staff_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ProductionPlan::class);
    }

    public function productionRuns(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
