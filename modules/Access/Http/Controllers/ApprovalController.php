<?php

declare(strict_types=1);

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\ApprovalStatus;
use Modules\Access\Models\ApprovalRequest;
use Modules\Access\Support\ApprovalService;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Tenancy\Models\Tenant;

final class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($request, $user);

        abort_unless($tenant instanceof Tenant, 403);

        $pending = $this->approvals->pendingForApprover($tenant, $user);

        $recent = ApprovalRequest::query()
            ->with(['requester', 'decider', 'branch'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [ApprovalStatus::Approved->value, ApprovalStatus::Rejected->value])
            ->latest('decided_at')
            ->limit(20)
            ->get();

        return view('access::admin.approvals', [
            'tenant' => $tenant,
            'pending' => $pending,
            'recent' => $recent,
            'labels' => collect(PermissionCatalogue::approvable())->map(fn ($a) => $a['name']),
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $user = $request->user();
        $tenant = Tenant::query()->findOrFail($approvalRequest->tenant_id);

        abort_unless(
            $user->is_platform_admin || $this->approvals->canApprove($approvalRequest, $user, $tenant),
            403,
        );

        $this->approvals->approve($approvalRequest, $user, $request->input('note'));

        app(\Modules\Access\Support\AuditLogger::class)->log($tenant->id, $user, 'approval.approved', "Approved: {$approvalRequest->title}.", 'approvals', 'approval_request', (string) $approvalRequest->id, [
            'type' => $approvalRequest->type,
            'amount_minor' => $approvalRequest->amount_minor,
        ]);

        return redirect()
            ->route('admin.access.approvals.index', $this->tenantParam($request))
            ->with('status', 'Request approved and applied.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $user = $request->user();
        $tenant = Tenant::query()->findOrFail($approvalRequest->tenant_id);

        abort_unless(
            $user->is_platform_admin || $this->approvals->canApprove($approvalRequest, $user, $tenant),
            403,
        );

        $this->approvals->reject($approvalRequest, $user, $request->input('note'));

        app(\Modules\Access\Support\AuditLogger::class)->log($tenant->id, $user, 'approval.rejected', "Rejected: {$approvalRequest->title}.", 'approvals', 'approval_request', (string) $approvalRequest->id, [
            'type' => $approvalRequest->type,
        ]);

        return redirect()
            ->route('admin.access.approvals.index', $this->tenantParam($request))
            ->with('status', 'Request rejected.');
    }

    private function tenant(Request $request, User $user): ?Tenant
    {
        return app(ActiveBranchManager::class)->stateForRequest($request, $user)['tenant'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function tenantParam(Request $request): array
    {
        $tenant = $request->query('tenant');

        return is_string($tenant) && $tenant !== '' ? ['tenant' => $tenant] : [];
    }
}
