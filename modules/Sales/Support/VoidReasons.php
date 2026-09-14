<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

/**
 * Reason codes for voiding food or drink after it was sent. A fixed list keeps the waste
 * report groupable; "Other" plus a note covers the rest.
 */
final class VoidReasons
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Guest changed their mind',
            'Wrong item entered',
            'Quality complaint',
            'Kitchen error',
            'Dropped or spilled',
            'On the house (comp)',
            'Walked out without paying',
            'Other',
        ];
    }
}
