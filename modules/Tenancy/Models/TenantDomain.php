<?php

declare(strict_types=1);

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantDomain extends Model
{
    public const TYPE_STOREBOOT = 'STOREBOOT_SUBDOMAIN';

    public const TYPE_CUSTOM = 'CUSTOM';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_VERIFYING = 'VERIFYING';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_FAILED = 'FAILED';

    public const SSL_PENDING = 'PENDING';

    public const SSL_PROVISIONING = 'PROVISIONING';

    public const SSL_ACTIVE = 'ACTIVE';

    public const SSL_FAILED = 'FAILED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'ssl_issued_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_dns_check_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isCustom(): bool
    {
        return $this->domain_type === self::TYPE_CUSTOM;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function verificationHost(): string
    {
        return '_storeboot.'.$this->registrableDomain();
    }

    public function verificationRecordValue(): string
    {
        return 'storeboot-verification='.$this->verification_token;
    }

    public function registrableDomain(): string
    {
        $labels = explode('.', $this->domain);

        if (count($labels) < 3) {
            return $this->domain;
        }

        $suffix = implode('.', array_slice($labels, -2));
        $labelCount = in_array($suffix, (array) config('storefront.compound_public_suffixes', []), true)
            ? 3
            : 2;

        return implode('.', array_slice($labels, -$labelCount));
    }

    public function isApex(): bool
    {
        return $this->domain === $this->registrableDomain();
    }

    public function dnsHostLabel(): string
    {
        if ($this->isApex()) {
            return '@';
        }

        return substr($this->domain, 0, -strlen('.'.$this->registrableDomain()));
    }
}
