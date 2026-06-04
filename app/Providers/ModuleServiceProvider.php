<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Modules\ModuleManifest;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/modules.php'), 'modules');

        foreach ((new ModuleManifest)->enabledProviders() as $provider) {
            $this->app->register($provider);
        }
    }
}
