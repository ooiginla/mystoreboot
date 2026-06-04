<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function moduleName(): string;

    public function boot(): void
    {
        $this->loadModuleRoutes();
        $this->loadModuleMigrations();

        $viewPath = $this->modulePath('resources/views');

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, $this->viewNamespace());
        }
    }

    protected function routePrefix(): string
    {
        return str($this->moduleName())->kebab()->value();
    }

    protected function viewNamespace(): string
    {
        return str($this->moduleName())->kebab()->value();
    }

    protected function modulePath(string $path = ''): string
    {
        return base_path('modules/'.$this->moduleName().($path !== '' ? '/'.$path : ''));
    }

    private function loadModuleRoutes(): void
    {
        $adminRoutes = $this->modulePath('routes/admin.php');

        if (file_exists($adminRoutes)) {
            Route::middleware(['web', 'auth'])
                ->prefix('admin/'.$this->routePrefix())
                ->name('admin.'.$this->routePrefix().'.')
                ->group($adminRoutes);
        }

        $apiRoutes = $this->modulePath('routes/api.php');

        if (file_exists($apiRoutes)) {
            Route::middleware(['api'])
                ->prefix('api/'.$this->routePrefix())
                ->name('api.'.$this->routePrefix().'.')
                ->group($apiRoutes);
        }

        $storefrontRoutes = $this->modulePath('routes/storefront.php');

        if (file_exists($storefrontRoutes)) {
            Route::middleware(['web'])
                ->name('storefront.'.$this->routePrefix().'.')
                ->group($storefrontRoutes);
        }
    }

    private function loadModuleMigrations(): void
    {
        $path = $this->modulePath('database/migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
