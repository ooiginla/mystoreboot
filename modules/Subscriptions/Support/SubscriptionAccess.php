<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Support;

use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\TenantSubscription;

final readonly class SubscriptionAccess
{
    public function __construct(
        public SubscriptionStatus $status,
        public bool $canLogin,
        public bool $canAccessTenant,
        public bool $canAccessModules,
        public bool $readOnlyRecommended,
        public string $meaning,
    ) {}

    public static function fromSubscription(TenantSubscription $subscription): self
    {
        return self::fromStatus($subscription->status);
    }

    public static function fromStatus(SubscriptionStatus $status): self
    {
        return new self(
            status: $status,
            canLogin: true,
            canAccessTenant: $status->allowsTenantAccess(),
            canAccessModules: $status->allowsModuleAccess(),
            readOnlyRecommended: $status->isReadOnlyRecommended(),
            meaning: $status->description(),
        );
    }
}
