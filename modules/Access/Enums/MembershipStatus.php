<?php

declare(strict_types=1);

namespace Modules\Access\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Invited => 'User has been invited but has not accepted yet.',
            self::Active => 'User can access this organization.',
            self::Inactive => 'User is disabled for this organization.',
            self::Suspended => 'User is blocked from this organization.',
        };
    }

    public function allowsTenantAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
