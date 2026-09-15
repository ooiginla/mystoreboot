<?php

declare(strict_types=1);

namespace Modules\Business\Support;

use Modules\Tenancy\Models\Tenant;

final class BankAccountNameVerification
{
    public static function required(Tenant $tenant): bool
    {
        return strtoupper((string) $tenant->country_code) === 'NG'
            || strtoupper((string) $tenant->currency_code) === 'NGN';
    }
}
