<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;

final class SupportTicket extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SupportTicketResponse::class);
    }
}
