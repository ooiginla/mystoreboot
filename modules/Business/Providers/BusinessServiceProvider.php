<?php

declare(strict_types=1);

namespace Modules\Business\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class BusinessServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Business';
    }
}
