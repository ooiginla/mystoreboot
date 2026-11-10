<?php

declare(strict_types=1);

namespace Modules\Tenancy\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Business\Models\OnlineStore;
use Modules\Tenancy\Contracts\DomainDnsResolver;
use Modules\Tenancy\Contracts\SslProvisioner;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantDomain;

final class TenantDomainService
{
    public function __construct(
        private readonly DomainDnsResolver $dns,
        private readonly SslProvisioner $ssl,
    ) {}

    public function normalize(string $input): string
    {
        $value = strtolower(trim($input));

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return strtolower(rtrim(trim(is_string($host) ? $host : ''), '.'));
    }

    public function managedDomainForStore(OnlineStore $store): string
    {
        $baseDomain = trim((string) config('storefront.base_domain')) ?: 'storeboot.com';
        $subdomain = trim((string) ($store->subdomain ?: $store->username));

        return strtolower($subdomain.'.'.$baseDomain);
    }

    public function syncStorebootDomain(OnlineStore $store): TenantDomain
    {
        $domain = $this->managedDomainForStore($store);

        return DB::transaction(function () use ($store, $domain): TenantDomain {
            $existing = TenantDomain::query()
                ->where('tenant_id', $store->tenant_id)
                ->where('domain_type', TenantDomain::TYPE_STOREBOOT)
                ->first();

            $isPrimary = $existing?->is_primary
                ?? ! TenantDomain::query()->where('tenant_id', $store->tenant_id)->where('is_primary', true)->exists();

            $managedDomain = $existing ?? new TenantDomain;
            $managedDomain->forceFill([
                'tenant_id' => $store->tenant_id,
                'domain' => $domain,
                'domain_type' => TenantDomain::TYPE_STOREBOOT,
                'status' => TenantDomain::STATUS_ACTIVE,
                'ssl_status' => TenantDomain::SSL_ACTIVE,
                'is_primary' => $isPrimary,
                'verified_at' => $managedDomain->verified_at ?? now(),
                'ssl_issued_at' => $managedDomain->ssl_issued_at ?? now(),
                'activated_at' => $managedDomain->activated_at ?? now(),
                'failure_reason' => null,
            ])->save();

            $this->forgetPrimaryDomain($store->tenant_id);

            return $managedDomain;
        });
    }

    public function connect(Tenant $tenant, string $domain): TenantDomain
    {
        $tenantDomain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
            'verification_token' => Str::lower(Str::random(40)),
            'ssl_status' => TenantDomain::SSL_PENDING,
            'is_primary' => false,
        ]);

        $this->forgetPrimaryDomain($tenant->id);

        return $tenantDomain;
    }

    /** @return array{ok: bool, message: string} */
    public function verify(TenantDomain $domain): array
    {
        $domain->forceFill([
            'status' => TenantDomain::STATUS_VERIFYING,
            'last_dns_check_at' => now(),
            'failure_reason' => null,
        ])->save();

        $this->forgetPrimaryDomain($domain->tenant_id);

        $expectedToken = $domain->verificationRecordValue();
        $txtRecords = $this->dns->txtRecords($domain->verificationHost());

        if (! in_array($expectedToken, $txtRecords, true)) {
            return $this->verificationFailed($domain, 'Verification TXT record was not found or its token does not match. DNS changes may still be propagating.');
        }

        $target = strtolower(rtrim(trim((string) config('storefront.custom_domain_target')), '.'));
        $cnames = $this->dns->cnameRecords($domain->domain);
        $pointsToTarget = $target !== '' && in_array($target, $cnames, true);

        if (! $pointsToTarget) {
            $targetAddresses = $this->dns->addresses($target);
            $domainAddresses = $this->dns->addresses($domain->domain);
            $configuredIp = trim((string) config('storefront.custom_domain_target_ip'));
            $acceptedAddresses = collect($targetAddresses)
                ->when($configuredIp !== '', fn ($addresses) => $addresses->push($configuredIp))
                ->unique();
            $pointsToTarget = $acceptedAddresses->intersect($domainAddresses)->isNotEmpty();
        }

        if (! $pointsToTarget) {
            return $this->verificationFailed($domain, "CNAME or A record does not point to {$target} yet.");
        }

        $domain->forceFill([
            'status' => TenantDomain::STATUS_VERIFIED,
            'verified_at' => now(),
            'failure_reason' => null,
        ])->save();
        $this->ssl->begin($domain);

        return [
            'ok' => true,
            'message' => 'Domain ownership and DNS connection verified. SSL provisioning is now pending.',
        ];
    }

    public function activate(TenantDomain $domain): void
    {
        $domain->forceFill([
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_ACTIVE,
            'ssl_issued_at' => now(),
            'activated_at' => now(),
            'failure_reason' => null,
        ])->save();
    }

    public function makePrimary(TenantDomain $domain): void
    {
        DB::transaction(function () use ($domain): void {
            TenantDomain::query()
                ->where('tenant_id', $domain->tenant_id)
                ->update(['is_primary' => false]);

            $domain->forceFill(['is_primary' => true])->save();
            $this->forgetPrimaryDomain($domain->tenant_id);
        });
    }

    public function remove(TenantDomain $domain): void
    {
        DB::transaction(function () use ($domain): void {
            $wasPrimary = $domain->is_primary;
            $tenantId = $domain->tenant_id;
            $domain->delete();

            if ($wasPrimary) {
                TenantDomain::query()
                    ->where('tenant_id', $tenantId)
                    ->where('domain_type', TenantDomain::TYPE_STOREBOOT)
                    ->update(['is_primary' => true]);
            }

            $this->forgetPrimaryDomain($tenantId);
        });
    }

    /** @return array{ok: false, message: string} */
    private function verificationFailed(TenantDomain $domain, string $message): array
    {
        $domain->forceFill([
            'status' => TenantDomain::STATUS_FAILED,
            'failure_reason' => $message,
        ])->save();

        return ['ok' => false, 'message' => $message];
    }

    private function forgetPrimaryDomain(string $tenantId): void
    {
        Cache::memo()->forget('tenant_domain:primary_custom:'.$tenantId);
    }
}
