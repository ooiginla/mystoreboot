<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinanceJournalEntry extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinanceJournalLine::class);
    }
}
