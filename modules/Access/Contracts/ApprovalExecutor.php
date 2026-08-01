<?php

declare(strict_types=1);

namespace Modules\Access\Contracts;

use App\Models\User;
use Modules\Access\Models\ApprovalRequest;

/**
 * Performs the deferred action captured by an approval request, once approved.
 * Implementations read $request->payload to reconstruct and execute the original action.
 */
interface ApprovalExecutor
{
    public function execute(ApprovalRequest $request, User $approver): void;
}
