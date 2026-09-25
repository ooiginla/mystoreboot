<?php

declare(strict_types=1);

namespace Modules\Reseller\Enums;

enum PricingMode: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case Combined = 'combined';
}
