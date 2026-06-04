<?php

declare(strict_types=1);

namespace Modules\Customers\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class CustomersServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Customers';
    }
}
