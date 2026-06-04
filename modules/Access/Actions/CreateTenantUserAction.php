<?php

declare(strict_types=1);

namespace Modules\Access\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\TenantMembership;

final class CreateTenantUserAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'is_platform_admin' => false,
                ],
            );

            TenantMembership::query()->updateOrCreate(
                [
                    'tenant_id' => $data['tenant_id'],
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $data['role_id'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            );

            return $user->refresh();
        });
    }
}
