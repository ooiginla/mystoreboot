<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Models\Tenant;

final class TillOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_float' => $this->cleanMoney($this->input('opening_float')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $branchId = $this->integer('branch_id');
        $tenant = $tenantId !== '' ? Tenant::query()->find($tenantId) : null;
        $inventoryEnabled = $tenant
            ? app(TenantModuleAccess::class)->allows($tenant, 'inventory')
            : true;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'inventory_location_id' => [
                Rule::requiredIf($inventoryEnabled),
                'nullable',
                'integer',
                Rule::exists('inventory_locations', 'id')->where(function ($query) use ($tenantId, $branchId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('status', 'active')
                        ->where('is_sellable_point', true)
                        ->where(function ($branchQuery) use ($branchId): void {
                            $branchQuery->whereNull('branch_id')->orWhere('branch_id', $branchId);
                        });
                }),
            ],
            'opening_float' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_location_id.required' => 'Choose the stock location this till will sell from.',
            'inventory_location_id.exists' => 'Choose an active sellable stock location for the selected branch.',
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
