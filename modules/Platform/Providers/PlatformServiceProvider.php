<?php

declare(strict_types=1);

namespace Modules\Platform\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class PlatformServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Platform';
    }
}
