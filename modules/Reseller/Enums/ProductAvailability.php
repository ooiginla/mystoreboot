<?php

declare(strict_types=1);

namespace Modules\Reseller\Enums;

enum ProductAvailability: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Unknown = 'unknown';
}
