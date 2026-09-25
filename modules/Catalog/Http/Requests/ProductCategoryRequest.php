<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;

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
        $category = $this->route('category');
        $uniqueSlug = Rule::unique('product_categories', 'slug')
            ->where('tenant_id', $this->string('tenant_id')->toString());

        if ($category instanceof ProductCategory) {
            $uniqueSlug->ignore($category->id);
        }

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'category_type' => ['required', Rule::in(array_column(CategoryType::cases(), 'value'))],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('tenant_id', $this->string('tenant_id')->toString())
                    ->where('category_type', $this->string('category_type')->toString()),
                Rule::notIn($category instanceof ProductCategory ? [$category->id] : []),
            ],
            'name' => ['required', 'string', 'max:140'],
            'slug' => [
                'required',
                'string',
                'max:160',
                $uniqueSlug,
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            $parentId = $this->integer('parent_id');

            if (! $category instanceof ProductCategory || ! $parentId || $validator->errors()->has('parent_id')) {
                return;
            }

            $parent = ProductCategory::query()->find($parentId);

            while ($parent) {
                if ($parent->id === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be placed under one of its descendants.');

                    return;
                }

                $parent = $parent->parent;
            }
        }];
    }
}
