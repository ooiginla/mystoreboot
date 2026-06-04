<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Modules\Business\Models\Department;

final class CreateDepartmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Department
    {
        return Department::query()->create([
            'tenant_id' => $data['tenant_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'name' => $data['name'],
            'code' => strtoupper((string) $data['code']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);
    }
}
