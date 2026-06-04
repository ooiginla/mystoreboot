<?php

declare(strict_types=1);

namespace Modules\Access\Actions;

use Illuminate\Support\Str;
use Modules\Access\Models\Role;

final class CreateRoleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Role
    {
        return Role::query()->create([
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'slug' => Str::slug((string) ($data['slug'] ?? $data['name'])),
            'is_system' => false,
        ]);
    }
}
