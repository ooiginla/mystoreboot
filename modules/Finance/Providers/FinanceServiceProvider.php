<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class FinanceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Finance';
    }
}
