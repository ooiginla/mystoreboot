<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Catalog';
    }
}
