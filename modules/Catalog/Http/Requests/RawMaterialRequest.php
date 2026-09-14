<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RawMaterialRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:120'],
            'unit_category_id' => [
                'nullable', 'integer',
                Rule::exists('unit_categories', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
        ];
    }
}
