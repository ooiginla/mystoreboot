<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancePettyCashTransaction extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceExpenseCategory::class, 'finance_expense_category_id');
    }
}
