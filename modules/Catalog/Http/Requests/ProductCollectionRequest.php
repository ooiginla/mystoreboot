<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_visible' => $this->boolean('is_visible'),
        ]);
    }

    public function rules(): array
    {
        $collectionId = $this->route('collection')?->id;
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('product_collections', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($collectionId),
            ],
            'is_visible' => ['required', 'boolean'],
        ];
    }
}
