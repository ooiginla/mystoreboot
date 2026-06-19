<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case GracePeriod = 'grace_period';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::GracePeriod => 'Grace period',
            self::Paused => 'Paused',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trialing => 'Free trial is running.',
            self::Active => 'Subscription is valid and current.',
            self::PastDue => 'Payment failed or renewal is overdue.',
            self::GracePeriod => 'Access is temporarily allowed until the current period ends.',
            self::Paused => 'Subscription is paused and should usually be read-only or limited.',
            self::Suspended => 'Subscription access is blocked.',
            self::Cancelled => 'Subscription has been cancelled.',
            self::Expired => 'Trial or subscription period has ended.',
        };
    }

    public function allowsTenantAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue, self::GracePeriod], true);
    }

    public function allowsModuleAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue, self::GracePeriod], true);
    }

    public function isReadOnlyRecommended(): bool
    {
        return $this === self::Paused;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
