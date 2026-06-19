<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesTillClosingCount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(SalesTillSession::class, 'sales_till_session_id');
    }
}
