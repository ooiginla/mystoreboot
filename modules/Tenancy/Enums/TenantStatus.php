<?php

declare(strict_types=1);

namespace Modules\Tenancy\Enums;

enum TenantStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trialing => 'Organization is in its trial period.',
            self::Active => 'Organization is active and can use enabled modules.',
            self::Inactive => 'Organization is temporarily disabled but retained.',
            self::Suspended => 'Organization access is blocked by billing, abuse, or admin action.',
            self::Cancelled => 'Organization subscription or account relationship has ended.',
        };
    }

    public function allowsTenantAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
