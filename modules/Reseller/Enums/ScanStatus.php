<?php

declare(strict_types=1);

namespace Modules\Reseller\Enums;

enum ScanStatus: string
{
    case NeverRun = 'never_run';
    case Running = 'running';
    case Successful = 'successful';
    case Partial = 'partial';
    case Failed = 'failed';
}
