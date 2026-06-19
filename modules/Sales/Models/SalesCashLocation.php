<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;
use Modules\Finance\Models\FinanceAccount;

final class SalesCashLocation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(SalesTillSession::class, 'sales_till_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financeAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class);
    }
}
