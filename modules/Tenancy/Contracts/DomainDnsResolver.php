<?php

declare(strict_types=1);

namespace Modules\Tenancy\Contracts;

interface DomainDnsResolver
{
    /** @return list<string> */
    public function txtRecords(string $hostname): array;

    /** @return list<string> */
    public function cnameRecords(string $hostname): array;

    /** @return list<string> */
    public function addresses(string $hostname): array;
}
