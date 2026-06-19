<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;

final class SalesTillSession extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashLocation(): BelongsTo
    {
        return $this->belongsTo(SalesCashLocation::class, 'cash_location_id');
    }

    public function vaultCashLocation(): BelongsTo
    {
        return $this->belongsTo(SalesCashLocation::class, 'vault_cash_location_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SalesTillMovement::class);
    }

    public function closingCounts(): HasMany
    {
        return $this->hasMany(SalesTillClosingCount::class);
    }
}
