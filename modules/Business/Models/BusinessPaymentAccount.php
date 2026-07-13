<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Models\FinanceAccount;

final class BusinessPaymentAccount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'supported_payment_methods' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function financeAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class);
    }

    public function supports(string $paymentMethod): bool
    {
        $canonical = $this->canonicalPaymentMethod($paymentMethod);

        return in_array($canonical, collect($this->supported_payment_methods ?? [])->map(fn ($method): string => $this->canonicalPaymentMethod((string) $method))->all(), true);
    }

    private function canonicalPaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match (true) {
            str_contains($method, 'card'), str_contains($method, 'pos') => 'card',
            str_contains($method, 'cheque'), str_contains($method, 'check') => 'cheque',
            str_contains($method, 'transfer'), str_contains($method, 'bank') => 'transfer',
            default => 'cash',
        };
    }
}
