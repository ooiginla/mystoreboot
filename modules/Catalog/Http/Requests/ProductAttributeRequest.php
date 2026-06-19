<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'values' => collect(explode(',', (string) $this->input('values')))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->unique(fn (string $value): string => strtolower($value))
                ->implode(', '),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attributeId = $this->route('attribute')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                Rule::unique('product_attribute_definitions', 'slug')->where('tenant_id', $this->string('tenant_id')->toString())->ignore($attributeId),
            ],
            'values' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $values = collect(explode(',', (string) $this->input('values')))
                ->map(fn (string $value): string => trim($value))
                ->filter();

            if ($values->isEmpty()) {
                $validator->errors()->add('values', 'Enter at least one possible value.');
            }
        });
    }
}
