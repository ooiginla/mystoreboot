<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletTransaction extends Model
{
    use BelongsToTenant;

    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    public const STATE_PENDING = 'pending';

    public const STATE_AVAILABLE = 'available';

    public const STATE_WITHDRAWN = 'withdrawn';

    public const STATE_REVERSED = 'reversed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'meta' => 'array',
            'available_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
