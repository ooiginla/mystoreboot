<?php

declare(strict_types=1);

namespace Modules\Storefront\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\View;
use Modules\Business\Models\OnlineStore;
use Modules\Storefront\Support\StorefrontUrl;

final class StorefrontServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        View::composer('storefront::*', function ($view): void {
            $view->with(
                'storefrontRoute',
                fn (OnlineStore $store, string $name = 'home', array $parameters = []): string => StorefrontUrl::route($store, $name, $parameters),
            );
        });
    }

    protected function moduleName(): string
    {
        return 'Storefront';
    }
}
