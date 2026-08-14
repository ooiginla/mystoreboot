<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wallet extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_balance_minor' => 'integer',
            'pending_balance_minor' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** Total of available + pending, in minor units. */
    public function totalBalanceMinor(): int
    {
        return (int) $this->available_balance_minor + (int) $this->pending_balance_minor;
    }
}
