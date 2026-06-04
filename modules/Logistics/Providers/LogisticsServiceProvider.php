<?php

declare(strict_types=1);

namespace Modules\Logistics\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class LogisticsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Logistics';
    }
}
