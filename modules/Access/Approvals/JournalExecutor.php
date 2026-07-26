<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Finance\Actions\PostJournalEntryAction;

/**
 * Posts an approved manual journal entry, mirroring FinanceReportController::storeJournalEntry.
 */
final class JournalExecutor implements ApprovalExecutor
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    public function execute(ApprovalRequest $request): void
    {
        $data = (array) ($request->payload['data'] ?? []);

        if ($data === []) {
            return;
        }

        $toMinor = static fn ($amount): int => (int) round(((float) str_replace(',', '', (string) ($amount ?? 0))) * 100);

        $this->postJournalEntry->execute(
            $data['tenant_id'],
            $data['entry_date'],
            $data['memo'],
            collect($data['lines'])->map(fn (array $line): array => [
                'account_code' => $line['account_code'],
                'debit_minor' => $toMinor($line['debit'] ?? 0),
                'credit_minor' => $toMinor($line['credit'] ?? 0),
                'branch_id' => $line['branch_id'] ?? null,
                'memo' => $line['memo'] ?? null,
            ])->all(),
            'manual_journal',
            null,
            null,
        );
    }
}
