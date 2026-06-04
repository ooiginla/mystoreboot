<?php

declare(strict_types=1);

namespace Modules\Tenancy\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class TenancyServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Tenancy';
    }
}
