<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InventoryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'name' => ['required', 'string', 'max:140'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('inventory_locations', 'code')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'location_type' => [
                'required',
                'string',
                'max:40',
                Rule::exists('location_types', 'key')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'is_sellable_point' => ['nullable', 'boolean'],
            'is_prep_station' => ['nullable', 'boolean'],
        ];
    }
}
