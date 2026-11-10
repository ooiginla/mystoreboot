<?php

declare(strict_types=1);

namespace Modules\Tenancy\Contracts;

use Modules\Tenancy\Models\TenantDomain;

interface SslProvisioner
{
    public function begin(TenantDomain $domain): void;
}
