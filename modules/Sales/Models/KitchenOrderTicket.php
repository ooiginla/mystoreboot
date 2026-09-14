<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Enums\TicketStatus;

final class KitchenOrderTicket extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'course' => 'integer',
            'fired_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'prep_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderTicketItem::class);
    }

    /**
     * Minutes since the ticket was fired. Drives the colour of the card on the kitchen
     * screen, so it is measured from fire, not from creation.
     */
    public function ageMinutes(): int
    {
        return (int) ($this->fired_at?->diffInMinutes(now()) ?? 0);
    }

    /**
     * Urgency band for the KDS. Thresholds are deliberately generous defaults; 6f makes
     * them per-tenant.
     */
    public function urgency(): string
    {
        if (! $this->status->isLive()) {
            return 'done';
        }

        return match (true) {
            $this->ageMinutes() >= 15 => 'late',
            $this->ageMinutes() >= 8 => 'warn',
            default => 'fresh',
        };
    }
}
