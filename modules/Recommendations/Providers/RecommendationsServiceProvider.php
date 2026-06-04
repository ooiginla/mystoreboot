<?php

declare(strict_types=1);

namespace Modules\Recommendations\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class RecommendationsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Recommendations';
    }
}
