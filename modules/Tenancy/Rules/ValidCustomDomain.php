<?php

declare(strict_types=1);

namespace Modules\Tenancy\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCustomDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(trim((string) $value));

        if ($domain === ''
            || strlen($domain) > 253
            || ! str_contains($domain, '.')
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
            || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || preg_match('/\.[a-z]{2,63}\z/i', $domain) !== 1) {
            $fail('Enter a valid public domain, such as www.example.com.');

            return;
        }

        $baseDomain = strtolower(trim((string) config('storefront.base_domain'))) ?: 'storeboot.test';
        $reserved = collect((array) config('storefront.reserved_domains', []))
            ->map(fn (mixed $item): string => strtolower(rtrim(trim((string) $item), '.')))
            ->when($baseDomain !== '', fn ($items) => $items->push($baseDomain))
            ->filter()
            ->unique();

        $isReserved = $reserved->contains(
            fn (string $item): bool => $domain === $item || str_ends_with($domain, '.'.$item),
        );

        if ($isReserved) {
            $fail('Storeboot-owned domains cannot be connected as custom domains.');
        }
    }
}
