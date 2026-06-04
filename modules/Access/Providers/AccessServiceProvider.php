<?php

declare(strict_types=1);

namespace Modules\Access\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class AccessServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Access';
    }
}
