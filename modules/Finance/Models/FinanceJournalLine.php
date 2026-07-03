<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;

final class FinanceJournalLine extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinanceJournalEntry::class, 'finance_journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
