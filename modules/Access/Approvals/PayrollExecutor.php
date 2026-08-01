<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use App\Models\User;
use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\HrPayroll\Actions\RunPayrollAction;

/**
 * Posts an approved payroll run using the same action the controller uses.
 * Figures are recomputed from live staff data at approval time.
 */
final class PayrollExecutor implements ApprovalExecutor
{
    public function __construct(private readonly RunPayrollAction $action) {}

    public function execute(ApprovalRequest $request, User $approver): void
    {
        $data = (array) ($request->payload['data'] ?? []);

        if ($data !== []) {
            $this->action->execute($data, $request->requested_by);
        }
    }
}
