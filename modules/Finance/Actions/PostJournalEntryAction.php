<?php

declare(strict_types=1);

namespace Modules\Finance\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournalEntry;

final class PostJournalEntryAction
{
    public function __construct(private readonly EnsureDefaultChartOfAccountsAction $ensureDefaultChartOfAccounts) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(
        string $tenantId,
        string $entryDate,
        string $memo,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $sourceEvent = null,
    ): ?FinanceJournalEntry {
        $lines = collect($lines)
            ->map(fn (array $line): array => [
                ...$line,
                'debit_minor' => (int) ($line['debit_minor'] ?? 0),
                'credit_minor' => (int) ($line['credit_minor'] ?? 0),
            ])
            ->filter(fn (array $line): bool => $line['debit_minor'] > 0 || $line['credit_minor'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $debits = (int) $lines->sum('debit_minor');
        $credits = (int) $lines->sum('credit_minor');

        if ($debits !== $credits) {
            throw ValidationException::withMessages([
                'journal' => 'Journal entry is not balanced.',
            ]);
        }

        return DB::transaction(function () use ($tenantId, $entryDate, $memo, $lines, $sourceType, $sourceId, $sourceEvent): FinanceJournalEntry {
            $this->ensureDefaultChartOfAccounts->execute($tenantId);

            if ($sourceType && $sourceId && $sourceEvent) {
                $existing = FinanceJournalEntry::query()
                    ->where('tenant_id', $tenantId)
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('source_event', $sourceEvent)
                    ->first();

                if ($existing) {
                    return $existing->load('lines.account');
                }
            }

            $entry = FinanceJournalEntry::query()->create([
                'tenant_id' => $tenantId,
                'entry_number' => $this->entryNumber($tenantId),
                'entry_date' => $entryDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_event' => $sourceEvent,
                'memo' => $memo,
            ]);

            foreach ($lines as $line) {
                $account = $this->account($tenantId, (string) $line['account_code']);

                $entry->lines()->create([
                    'tenant_id' => $tenantId,
                    'finance_account_id' => $account->id,
                    'party_type' => $line['party_type'] ?? null,
                    'party_id' => $line['party_id'] ?? null,
                    'debit_minor' => $line['debit_minor'],
                    'credit_minor' => $line['credit_minor'],
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->refresh()->load('lines.account');
        });
    }

    private function account(string $tenantId, string $code): FinanceAccount
    {
        return FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function entryNumber(string $tenantId): string
    {
        return 'JE-'.now()->format('Ymd').'-'.str_pad((string) (FinanceJournalEntry::query()->where('tenant_id', $tenantId)->count() + 1), 6, '0', STR_PAD_LEFT);
    }
}
