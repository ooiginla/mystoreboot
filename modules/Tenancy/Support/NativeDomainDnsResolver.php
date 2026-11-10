<?php

declare(strict_types=1);

namespace Modules\Tenancy\Support;

use Modules\Tenancy\Contracts\DomainDnsResolver;

final class NativeDomainDnsResolver implements DomainDnsResolver
{
    public function txtRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT);

        return collect(is_array($records) ? $records : [])
            ->map(fn (array $record): string => (string) ($record['txt'] ?? implode('', $record['entries'] ?? [])))
            ->filter()
            ->values()
            ->all();
    }

    public function cnameRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_CNAME);

        return collect(is_array($records) ? $records : [])
            ->pluck('target')
            ->filter()
            ->map(fn (string $target): string => strtolower(rtrim($target, '.')))
            ->values()
            ->all();
    }

    public function addresses(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);

        return collect(is_array($records) ? $records : [])
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
