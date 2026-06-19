<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderSettingRequest extends FormRequest
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
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:999999999'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
