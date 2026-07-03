<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OnlinePaymentSettlement extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'settled_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OnlineCollectedPayment::class);
    }
}
