<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Business\Models\Branch;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;

final class ProductionPlan extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['production_date' => 'date', 'planned_quantity' => 'decimal:4'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
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

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkOrder::class, 'production_work_order_id');
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(HrStaff::class, 'production_plan_staff')->withPivot('is_lead')->withTimestamps();
    }
}
