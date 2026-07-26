<?php

declare(strict_types=1);

namespace Modules\Access\Support;

use Illuminate\Support\Collection;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;

/**
 * Surfaces risky access configurations for the access-review page:
 * self-approval (separation-of-duties), privilege concentration, and gaps.
 *
 * @phpstan-type Finding array{severity: string, title: string, detail: string, subject: string}
 */
final class AccessRiskAnalyzer
{
    /**
     * @param  Collection<int, Role>  $roles           roles with their permissions loaded
     * @param  Collection<int, TenantMembership>  $memberships  memberships with role + user
     * @return list<Finding>
     */
    public function analyze(Collection $roles, Collection $memberships): array
    {
        $findings = [];
        $approvable = PermissionCatalogue::approvable();

        foreach ($roles as $role) {
            if ($role->is_protected) {
                continue; // the owner is expected to have everything
            }

            $slugs = array_flip($role->permissionSlugs());

            // Separation of duties: can both submit and approve the same action.
            foreach ($approvable as $type => $meta) {
                if ($meta['request'] === $meta['approve']) {
                    continue;
                }

                if (isset($slugs[$meta['request']], $slugs[$meta['approve']])) {
                    $findings[] = [
                        'severity' => 'high',
                        'title' => 'Self-approval possible — '.$meta['name'],
                        'detail' => "The “{$role->name}” role can both submit and approve {$meta['name']}. One person could bypass the approval step.",
                        'subject' => $role->name,
                    ];
                }
            }

            // Privilege concentration: can manage roles/permissions.
            if (isset($slugs['roles.manage'])) {
                $findings[] = [
                    'severity' => 'medium',
                    'title' => 'Can manage roles & permissions',
                    'detail' => "The “{$role->name}” role can create and edit roles, so members can grant themselves any permission.",
                    'subject' => $role->name,
                ];
            }

            // Can issue refunds with no cap.
            if (isset($slugs['sales.refunds.issue']) && $role->limit('sales.refund.max_minor') === null) {
                $findings[] = [
                    'severity' => 'low',
                    'title' => 'Unlimited refunds',
                    'detail' => "The “{$role->name}” role can issue refunds of any amount. Consider setting a refund limit.",
                    'subject' => $role->name,
                ];
            }
        }

        // Members with no role at all.
        $noRole = $memberships->filter(fn (TenantMembership $m): bool => $m->role_id === null && $m->status->value === 'active');
        foreach ($noRole as $m) {
            $findings[] = [
                'severity' => 'low',
                'title' => 'User has no role',
                'detail' => "{$m->user?->name} is a member but has no role, so they cannot access anything.",
                'subject' => $m->user?->name ?? 'Unknown user',
            ];
        }

        // Owner concentration.
        $ownerCount = $memberships->filter(fn (TenantMembership $m): bool => $m->role?->slug === 'business-owner' && $m->status->value === 'active')->count();
        if ($ownerCount > 3) {
            $findings[] = [
                'severity' => 'medium',
                'title' => 'Many Business Owners',
                'detail' => "{$ownerCount} people have full, unrestricted access. Keep the number of Business Owners small.",
                'subject' => 'Business Owner',
            ];
        }

        // Highest severity first.
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($findings, fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return $findings;
    }
}
