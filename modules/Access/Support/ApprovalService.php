<?php

declare(strict_types=1);

namespace Modules\Access\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Enums\ApprovalStatus;
use Modules\Access\Models\ApprovalRequest;
use Modules\Tenancy\Models\Tenant;

/**
 * Central approval-workflow service.
 *
 *  - requiresApproval(): does the tenant require approval for this action? (Business Settings)
 *  - create():          record a pending approval request capturing the deferred action.
 *  - approve():         mark approved and run the registered executor to perform the action.
 *  - reject():          mark rejected.
 *  - pendingForApprover(): the queue a given user is allowed to action.
 *
 * Executors are bound per approval type (see PermissionCatalogue::approvable()) and
 * know how to perform the original action from the stored payload.
 */
final class ApprovalService
{
    /** @var array<string, class-string<ApprovalExecutor>> */
    private array $executors = [];

    public function __construct(private readonly PermissionService $permissions) {}

    /**
     * @param  class-string<ApprovalExecutor>  $executor
     */
    public function registerExecutor(string $type, string $executor): void
    {
        $this->executors[$type] = $executor;
    }

    public function requiresApproval(Tenant $tenant, string $type): bool
    {
        return $this->permissions->requiresApproval($tenant, $type);
    }

    /**
     * Whether the acting user must route this action through approval: the tenant
     * requires approval for the type AND the user cannot approve it themselves.
     * Platform admins never divert. Users who hold the approve permission act directly
     * (self-approval — surfaced as a risk in the access review).
     */
    public function shouldDivert(Tenant $tenant, User $user, string $type, string $approvePermission): bool
    {
        if ($user->is_platform_admin) {
            return false;
        }

        if (! $this->requiresApproval($tenant, $type)) {
            return false;
        }

        return ! $this->permissions->has($user, $tenant, $approvePermission);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Tenant $tenant,
        User $requester,
        string $type,
        string $title,
        array $attributes = [],
    ): ApprovalRequest {
        return ApprovalRequest::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $attributes['branch_id'] ?? null,
            'type' => $type,
            'status' => ApprovalStatus::Pending,
            'title' => $title,
            'description' => $attributes['description'] ?? null,
            'amount_minor' => $attributes['amount_minor'] ?? null,
            'payload' => $attributes['payload'] ?? null,
            'request_note' => $attributes['request_note'] ?? null,
            'requested_by' => $requester->id,
        ]);
    }

    public function approve(ApprovalRequest $request, User $approver, ?string $note = null): ApprovalRequest
    {
        abort_unless($request->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        return DB::transaction(function () use ($request, $approver, $note): ApprovalRequest {
            $executor = $this->executors[$request->type] ?? null;

            if ($executor !== null) {
                app($executor)->execute($request, $approver);
            }

            $request->update([
                'status' => ApprovalStatus::Approved,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $request->refresh();
        });
    }

    public function reject(ApprovalRequest $request, User $approver, ?string $note = null): ApprovalRequest
    {
        abort_unless($request->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        $request->update([
            'status' => ApprovalStatus::Rejected,
            'decided_by' => $approver->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $request->refresh();
    }

    /**
     * The pending requests a user may act on: the approval permission for that type,
     * scoped to their branch.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ApprovalRequest>
     */
    public function pendingForApprover(Tenant $tenant, User $user)
    {
        $approvableTypes = $this->typesApprovableBy($user, $tenant);

        return ApprovalRequest::query()
            ->with(['requester', 'branch'])
            ->where('tenant_id', $tenant->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->whereIn('type', $approvableTypes ?: ['__none__'])
            ->where(function ($q) use ($user, $tenant): void {
                // Branch scope: all-branch approvers see everything; branch-scoped see their branch.
                $membership = $this->permissions->membership($user, $tenant);
                if ($membership && $membership->branch_id !== null) {
                    $q->where(function ($inner) use ($membership): void {
                        $inner->whereNull('branch_id')->orWhere('branch_id', $membership->branch_id);
                    });
                }
            })
            ->latest()
            ->get();
    }

    public function canApprove(ApprovalRequest $request, User $user, Tenant $tenant): bool
    {
        return in_array($request->type, $this->typesApprovableBy($user, $tenant), true)
            && $this->permissions->allowsBranch($user, $tenant, $request->branch_id);
    }

    /**
     * @return list<string>
     */
    public function typesApprovableBy(User $user, Tenant $tenant): array
    {
        $types = [];

        foreach (PermissionCatalogue::approvable() as $type => $meta) {
            if ($this->permissions->has($user, $tenant, $meta['approve'])) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
