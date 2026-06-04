<?php

declare(strict_types=1);

namespace Modules\Analytics\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Analytics';
    }
}
