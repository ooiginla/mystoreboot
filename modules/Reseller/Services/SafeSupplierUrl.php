<?php

declare(strict_types=1);

namespace Modules\Reseller\Services;

use InvalidArgumentException;

final class SafeSupplierUrl
{
    public function assert(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Supplier URLs must use HTTP or HTTPS.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Local supplier URLs are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Private or reserved supplier addresses are not allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Supplier URLs cannot contain credentials.');
        }
    }

    public function sameHost(string $candidate, string $source): bool
    {
        return strtolower((string) parse_url($candidate, PHP_URL_HOST))
            === strtolower((string) parse_url($source, PHP_URL_HOST));
    }
}
