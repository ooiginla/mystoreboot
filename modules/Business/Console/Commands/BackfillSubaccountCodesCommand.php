<?php

declare(strict_types=1);

namespace Modules\Business\Console\Commands;

use Illuminate\Console\Command;
use Modules\Business\Models\OnlineStore;
use Modules\Business\Support\PaystackDirectory;

/**
 * Backfills settlement_bank_account.subaccount_code for stores whose Paystack subaccount was
 * created before the create-response bug was fixed (so the code was never stored). It matches
 * existing Paystack subaccounts to stores by bank code + account number — it does NOT create
 * any new subaccounts, so there are no duplicates.
 *
 *   php artisan storeboot:backfill-subaccounts            # apply
 *   php artisan storeboot:backfill-subaccounts --dry-run  # preview only
 */
final class BackfillSubaccountCodesCommand extends Command
{
    protected $signature = 'storeboot:backfill-subaccounts {--dry-run : Show what would change without saving}';

    protected $description = 'Fill in missing Paystack subaccount_code on stores by matching existing Paystack subaccounts.';

    public function handle(PaystackDirectory $paystack): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $paystack->configured()) {
            $this->error('Paystack is not configured (missing secret key).');

            return self::FAILURE;
        }

        $this->info('Fetching subaccounts from Paystack…');
        $subaccounts = $paystack->listSubaccounts();

        if ($subaccounts === []) {
            $this->warn('No subaccounts returned from Paystack. Nothing to match against.');

            return self::SUCCESS;
        }

        // Group by account number (unique per merchant); disambiguate by bank code only when
        // one account number has several subaccounts. This tolerates bank-code format quirks.
        $byAccount = [];
        foreach ($subaccounts as $sub) {
            if ($sub['account_number'] !== '' && $sub['subaccount_code'] !== '') {
                $byAccount[$sub['account_number']][] = $sub;
            }
        }
        $this->info(count($subaccounts).' subaccount(s) fetched.');

        $filled = 0;
        $unmatched = 0;
        $rows = [];

        OnlineStore::query()->chunkById(200, function ($stores) use ($byAccount, $dryRun, &$filled, &$unmatched, &$rows): void {
            foreach ($stores as $store) {
                $settlement = (array) ($store->payment_settings['settlement_bank_account'] ?? []);
                $bankCode = (string) ($settlement['bank_code'] ?? '');
                $accountNumber = (string) ($settlement['account_number'] ?? '');

                // Skip stores that already have a code, or have no account set.
                if (filled($settlement['subaccount_code'] ?? null) || $accountNumber === '') {
                    continue;
                }

                $candidates = $byAccount[$accountNumber] ?? [];
                $code = match (true) {
                    count($candidates) === 1 => $candidates[0]['subaccount_code'],
                    count($candidates) > 1 => (collect($candidates)->firstWhere('bank_code', $bankCode)['subaccount_code'] ?? null),
                    default => null,
                };

                if (! $code) {
                    $unmatched++;
                    $rows[] = [$store->store_name, $bankCode, $accountNumber, count($candidates) > 1 ? 'AMBIGUOUS' : 'NO MATCH'];

                    continue;
                }

                $rows[] = [$store->store_name, $bankCode, $accountNumber, $code];
                $filled++;

                if (! $dryRun) {
                    $paymentSettings = $store->payment_settings ?? [];
                    $paymentSettings['settlement_bank_account']['subaccount_code'] = $code;
                    $store->forceFill(['payment_settings' => $paymentSettings])->save();
                }
            }
        });

        if ($rows !== []) {
            $this->table(['Store', 'Bank', 'Account', 'Subaccount code'], $rows);
        }

        $verb = $dryRun ? 'would be filled' : 'filled';
        $this->info("{$filled} store(s) {$verb}; {$unmatched} unmatched (no Paystack subaccount for that bank + account).");

        if ($dryRun) {
            $this->warn('Dry run — no changes saved. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
