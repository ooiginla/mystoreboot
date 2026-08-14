<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Support\Wallet\WalletService;

/**
 * Given the transaction references contained in a completed gateway settlement, flip the
 * matching wallet credits from Pending to Available. Provider-neutral: it works purely off
 * references, so the gateway that produced them is irrelevant.
 *
 * Idempotent — reprocessing the same settlement flips nothing that is already available.
 */
final class ReconcileWalletSettlementAction
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * @param  list<string>  $references
     * @return int  How many wallet credits were moved to Available.
     */
    public function execute(array $references, string $settlementId): int
    {
        $flipped = 0;

        foreach ($references as $reference) {
            $orderId = $this->orderIdForReference($reference);

            if (! $orderId) {
                continue;
            }

            if ($this->wallet->markSaleAvailable($orderId, $settlementId)) {
                $flipped++;
            }

            // Mark the underlying collection settled — for wallet modes, "settled" means the
            // funds have reached Storeboot (and are now withdrawable).
            OnlineCollectedPayment::query()
                ->where('provider_reference', $reference)
                ->whereNull('settled_at')
                ->update(['is_settled' => true, 'settled_at' => now()]);
        }

        return $flipped;
    }

    private function orderIdForReference(string $reference): ?int
    {
        // Primary: the collected-payment record we wrote at charge time maps reference → order.
        $orderId = OnlineCollectedPayment::query()
            ->where('provider_reference', $reference)
            ->value('sales_order_id');

        if ($orderId) {
            return (int) $orderId;
        }

        // Fallback: our references are minted as "PSK-{orderId}-{random}".
        if (preg_match('/^PSK-(\d+)-/i', $reference, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
