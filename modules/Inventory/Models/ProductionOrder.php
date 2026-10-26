<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\ProductVariant;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;
use Modules\Inventory\Enums\ProductionVarianceReason;

final class ProductionOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'actual_yield_quantity' => 'decimal:4',
            'variance_reason' => ProductionVarianceReason::class,
            'produced_at' => 'datetime',
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Started, ingredients held, nothing deducted yet. */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Completed',
        };
    }

    /** Stable, user-facing identifier used to trace this run across production and waste. */
    public function runNumber(): string
    {
        return 'RUN-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function outputVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'output_product_variant_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function outputLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'output_location_id');
    }

    public function productionLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'production_lead_user_id');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(HrWorkShift::class, 'hr_work_shift_id');
    }

    public function productionLeadStaff(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'production_lead_staff_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkOrder::class, 'production_work_order_id');
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(HrStaff::class, 'production_order_staff')->withPivot('is_lead')->withTimestamps();
    }
}
