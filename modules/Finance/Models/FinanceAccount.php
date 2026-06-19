<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinanceAccount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(FinanceJournalLine::class);
    }
}
