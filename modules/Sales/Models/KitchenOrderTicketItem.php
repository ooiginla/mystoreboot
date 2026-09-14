<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KitchenOrderTicketItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KitchenOrderTicket::class, 'kitchen_order_ticket_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
