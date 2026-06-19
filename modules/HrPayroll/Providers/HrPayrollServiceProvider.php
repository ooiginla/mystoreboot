<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class HrPayrollServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'HrPayroll';
    }
}
