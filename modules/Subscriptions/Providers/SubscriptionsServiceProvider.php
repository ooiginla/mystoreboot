<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Providers;

use App\Support\Modules\ModuleServiceProvider;

final class SubscriptionsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Subscriptions';
    }
}
