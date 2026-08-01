<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use App\Models\User;
use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Finance\Actions\RecordExpenseAction;

/**
 * Records an approved expense using the same action the controller uses.
 */
final class ExpenseExecutor implements ApprovalExecutor
{
    public function __construct(private readonly RecordExpenseAction $action) {}

    public function execute(ApprovalRequest $request, User $approver): void
    {
        $data = (array) ($request->payload['data'] ?? []);

        if ($data !== []) {
            $this->action->execute($data);
        }
    }
}
