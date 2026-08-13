<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

use Modules\Tenancy\Models\Tenant;

/**
 * How a business receives its online (gateway) earnings. Set per business and only
 * changeable by a Storeboot platform admin, because modes 2 & 3 involve Storeboot
 * holding (and, for instant, advancing) merchant funds.
 */
enum PayoutMode: string
{
    /** Split at checkout; Paystack settles the merchant's share directly to their bank (T+1). No wallet, no custody. */
    case AutoSubaccount = 'auto_subaccount';

    /** Earnings held in a Storeboot wallet; withdrawable once Paystack has settled the funds to Storeboot. */
    case WalletOnSettlement = 'wallet_on_settlement';

    /** Earnings available in the wallet immediately; Storeboot advances the funds before settlement. */
    case WalletInstant = 'wallet_instant';

    public static function default(): self
    {
        return self::AutoSubaccount;
    }

    public static function fromTenant(Tenant $tenant): self
    {
        return self::tryFrom((string) ($tenant->settings['payout_mode'] ?? '')) ?? self::default();
    }

    public function label(): string
    {
        return match ($this) {
            self::AutoSubaccount => 'Auto-settle to bank (T+1)',
            self::WalletOnSettlement => 'Wallet — pay out after settlement',
            self::WalletInstant => 'Wallet — instant payout',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AutoSubaccount => 'Each online sale is split at checkout and Paystack pays the business share straight to their bank the next day. No balance is held by Storeboot.',
            self::WalletOnSettlement => 'Online earnings collect into a Storeboot wallet and can be withdrawn once Paystack has settled the funds. Storeboot holds the balance.',
            self::WalletInstant => 'Online earnings are available to withdraw immediately, before Paystack settles. Storeboot advances the funds and carries the risk.',
        };
    }

    /** Does this mode require Storeboot to custody merchant funds? */
    public function isCustodial(): bool
    {
        return $this !== self::AutoSubaccount;
    }

    public function tone(): string
    {
        return match ($this) {
            self::AutoSubaccount => 'success',
            self::WalletOnSettlement => 'warning',
            self::WalletInstant => 'danger',
        };
    }
}
