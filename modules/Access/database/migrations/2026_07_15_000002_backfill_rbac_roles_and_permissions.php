<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Access\Actions\EnsureSystemRolesAction;
use Modules\Access\Actions\SyncPermissionCatalogueAction;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Tenancy\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed the atomic permission catalogue.
        app(SyncPermissionCatalogueAction::class)->execute();

        $ensureRoles = app(EnsureSystemRolesAction::class);

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($ensureRoles): void {
            // 2. Promote the legacy self-signup "Administrator" role to the protected Business Owner.
            $hasOwner = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'business-owner')->exists();

            if (! $hasOwner) {
                $legacy = Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('slug', ['administrator', 'admin', 'owner'])
                    ->orderByDesc('is_system')
                    ->first();

                $legacy?->update([
                    'slug' => 'business-owner',
                    'name' => 'Business Owner',
                    'is_system' => true,
                    'is_protected' => true,
                    'template_key' => 'business-owner',
                ]);
            }

            // 3. Ensure every template role exists with real permissions.
            $ensureRoles->execute($tenant->id, $tenant->currency_code ?: 'NGN');

            // 4. Lockout protection — guarantee at least one active Business Owner.
            $ownerRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'business-owner')->first();

            if ($ownerRole) {
                $hasOwnerMember = TenantMembership::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('role_id', $ownerRole->id)
                    ->where('status', MembershipStatus::Active->value)
                    ->exists();

                if (! $hasOwnerMember) {
                    $earliest = TenantMembership::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('status', MembershipStatus::Active->value)
                        ->orderBy('id')
                        ->first();

                    $earliest?->update(['role_id' => $ownerRole->id]);
                }
            }

            // 5. Turn on enforcement (deny-by-default) with approvals off until configured.
            $settings = $tenant->settings ?? [];
            $settings['rbac_enforced'] = true;
            $settings['approvals'] ??= ['enabled' => false, 'actions' => []];
            $tenant->update(['settings' => $settings]);
        });
    }

    public function down(): void
    {
        Tenant::query()->cursor()->each(function (Tenant $tenant): void {
            $settings = $tenant->settings ?? [];
            unset($settings['rbac_enforced']);
            $tenant->update(['settings' => $settings]);
        });
    }
};
