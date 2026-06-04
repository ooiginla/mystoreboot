<?php

declare(strict_types=1);

namespace Modules\Storefront\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class StorefrontServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Storefront';
    }
}
