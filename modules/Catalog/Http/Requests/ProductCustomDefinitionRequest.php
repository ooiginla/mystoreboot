<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductCustomDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'values' => collect(explode(',', (string) $this->input('values')))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->unique(fn (string $value): string => strtolower($value))
                ->implode(', '),
            'is_customer_selectable' => $this->boolean('is_customer_selectable'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:80', Rule::unique('product_custom_definitions', 'name')->where('tenant_id', $this->string('tenant_id')->toString())],
            'values' => ['required', 'string', 'max:1000'],
            'is_customer_selectable' => ['boolean'],
        ];
    }
}
