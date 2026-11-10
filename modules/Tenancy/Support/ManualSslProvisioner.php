<?php

declare(strict_types=1);

namespace Modules\Tenancy\Support;

use Modules\Tenancy\Contracts\SslProvisioner;
use Modules\Tenancy\Models\TenantDomain;

final class ManualSslProvisioner implements SslProvisioner
{
    public function begin(TenantDomain $domain): void
    {
        $domain->forceFill([
            'ssl_status' => TenantDomain::SSL_PROVISIONING,
        ])->save();
    }
}
