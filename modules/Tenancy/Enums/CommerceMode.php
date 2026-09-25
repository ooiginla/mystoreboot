<?php

declare(strict_types=1);

namespace Modules\Tenancy\Enums;

enum CommerceMode: string
{
    case Standard = 'standard';
    case Reseller = 'reseller';
}
