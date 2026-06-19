<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Catalog\Enums\CategoryType;

final class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'category_type' => ['required', Rule::in(array_column(CategoryType::cases(), 'value'))],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('tenant_id', $this->string('tenant_id')->toString())
                    ->where('category_type', $this->string('category_type')->toString()),
            ],
            'name' => ['required', 'string', 'max:140'],
            'slug' => [
                'required',
                'string',
                'max:160',
                Rule::unique('product_categories', 'slug')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
