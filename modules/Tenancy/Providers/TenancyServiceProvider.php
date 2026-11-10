<?php

declare(strict_types=1);

namespace Modules\Tenancy\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Modules\Tenancy\Contracts\DomainDnsResolver;
use Modules\Tenancy\Contracts\SslProvisioner;
use Modules\Tenancy\Support\ManualSslProvisioner;
use Modules\Tenancy\Support\NativeDomainDnsResolver;

final class TenancyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainDnsResolver::class, NativeDomainDnsResolver::class);
        $this->app->bind(SslProvisioner::class, ManualSslProvisioner::class);
    }

    protected function moduleName(): string
    {
        return 'Tenancy';
    }
}
