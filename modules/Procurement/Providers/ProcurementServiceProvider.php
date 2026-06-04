<?php

declare(strict_types=1);

namespace Modules\Procurement\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class ProcurementServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Procurement';
    }
}
