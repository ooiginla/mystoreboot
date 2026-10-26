<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerCreditPolicy: string
{
    case Inherit = 'inherit';
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Inherit => 'Use group policy',
            self::Enabled => 'Allow credit',
            self::Disabled => 'Do not allow credit',
        };
    }
}
