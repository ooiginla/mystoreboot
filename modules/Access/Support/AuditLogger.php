<?php

declare(strict_types=1);

namespace Modules\Access\Support;

use App\Models\User;
use Illuminate\Support\Facades\Request;
use Modules\Access\Models\SecurityAuditLog;

/**
 * Records security-relevant events (role changes, access assignments, approval
 * decisions, permission denials) to an immutable audit trail.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $tenantId,
        ?User $actor,
        string $action,
        string $description,
        string $category = 'access',
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
    ): void {
        SecurityAuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'category' => $category,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => mb_substr($description, 0, 400),
            'metadata' => $metadata ?: null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
