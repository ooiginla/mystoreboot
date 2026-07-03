<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductAttributeValue;
use Modules\Catalog\Models\ProductTax;
use Modules\Catalog\Models\ProductVariant;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'sku' => $this->filled('sku') ? strtoupper((string) $this->input('sku')) : null,
            'barcode' => $this->filled('barcode') ? strtoupper((string) $this->input('barcode')) : null,
            'has_variants' => $this->boolean('has_variants'),
            'base_price' => $this->cleanMoney($this->input('base_price')),
            'base_cost_price' => $this->cleanMoney($this->input('base_cost_price')),
            'compare_at_price' => $this->cleanMoney($this->input('compare_at_price', $this->input('discount_price'))),
            'variants' => collect((array) $this->input('variants', []))
                ->map(function (array $variant): array {
                    $variant['sku'] = isset($variant['sku']) && trim((string) $variant['sku']) !== '' ? strtoupper((string) $variant['sku']) : null;
                    $variant['barcode'] = isset($variant['barcode']) && trim((string) $variant['barcode']) !== '' ? strtoupper((string) $variant['barcode']) : null;
                    $variant['selling_price'] = $this->cleanMoney($variant['selling_price'] ?? null);
                    $variant['cost_price'] = $this->cleanMoney($variant['cost_price'] ?? null);
                    $variant['compare_at_price'] = $this->cleanMoney($variant['compare_at_price'] ?? $variant['discount_price'] ?? null);

                    return $variant;
                })
                ->all(),
            'options' => collect((array) $this->input('options', []))
                ->map(function (array $option): array {
                    $option['name'] = trim((string) ($option['name'] ?? ''));
                    $option['values'] = trim((string) ($option['values'] ?? ''));

                    return $option;
                })
                ->all(),
            'tag_ids' => array_values((array) $this->input('tag_ids', [])),
            'attribute_value_ids' => array_values((array) $this->input('attribute_value_ids', [])),
            'tax_ids' => array_values((array) $this->input('tax_ids', [])),
            'new_tags' => $this->cleanCommaList($this->input('new_tags')),
            'new_attribute_values' => collect((array) $this->input('new_attribute_values', []))
                ->map(fn (mixed $values): string => $this->cleanCommaList($values))
                ->all(),
            'new_attributes' => collect((array) $this->input('new_attributes', []))
                ->map(fn (array $attribute): array => [
                    'name' => trim((string) ($attribute['name'] ?? '')),
                    'values' => $this->cleanCommaList($attribute['values'] ?? ''),
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('tenant_id', $this->string('tenant_id')->toString())
                    ->where('category_type', $this->categoryTypeForProductType()),
            ],
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'required',
                'string',
                'max:200',
                Rule::unique('products', 'slug')->where('tenant_id', $this->string('tenant_id')->toString())->ignore($productId),
            ],
            'brand' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', Rule::in(array_column(ProductType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:4000'],
            'has_variants' => ['boolean'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'base_cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_behavior' => ['required', Rule::in(array_column(TaxBehavior::cases(), 'value'))],
            'tax_ids' => ['nullable', 'array'],
            'tax_ids.*' => ['integer', Rule::exists('product_taxes', 'id')->where('tenant_id', $this->string('tenant_id')->toString())],
            'status' => ['required', Rule::in(ProductStatus::values())],
            'image' => ['nullable', 'image', 'max:2048'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'max:4096'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')->where('tenant_id', $this->string('tenant_id')->toString())],
            'new_tags' => ['nullable', 'string', 'max:1000'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer'],
            'new_attribute_values' => ['nullable', 'array'],
            'new_attribute_values.*' => ['nullable', 'string', 'max:2000'],
            'new_attributes' => ['nullable', 'array', 'max:10'],
            'new_attributes.*.name' => ['nullable', 'string', 'max:120'],
            'new_attributes.*.values' => ['nullable', 'string', 'max:2000'],
            'sku' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('product_variants', 'sku')->where('tenant_id', $this->string('tenant_id')->toString())->ignore($this->route('product')?->variants()->oldest('id')->value('id')),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('product_variants', 'barcode')->where('tenant_id', $this->string('tenant_id')->toString())->ignore($this->route('product')?->variants()->oldest('id')->value('id')),
            ],
            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['nullable', 'string', 'max:80'],
            'options.*.values' => ['nullable', 'string', 'max:1000'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.option_signature' => ['nullable', 'string', 'max:1000'],
            'variants.*.variant_name' => ['nullable', 'string', 'max:220'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.barcode' => ['nullable', 'string', 'max:120'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.status' => ['nullable', Rule::in(ProductStatus::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateVariantTenantOwnership($validator);
            $this->validateVariantIdentifiers($validator, 'sku');
            $this->validateVariantIdentifiers($validator, 'barcode');
            $this->validateOptions($validator);
            $this->validateTaxes($validator);
            $this->validateAttributeValues($validator);
            $this->validateInlineAttributeValues($validator);
            $this->validateInlineAttributes($validator);
        });
    }

    private function categoryTypeForProductType(): string
    {
        return $this->string('product_type')->toString() === ProductType::Service->value
            ? CategoryType::Service->value
            : CategoryType::Product->value;
    }

    private function validateVariantTenantOwnership(Validator $validator): void
    {
        $product = $this->route('product');
        $tenantId = $this->string('tenant_id')->toString();

        foreach ((array) $this->input('variants', []) as $index => $variant) {
            $variantId = $variant['id'] ?? null;

            if (! $variantId) {
                continue;
            }

            $belongsToProduct = $product
                ? ProductVariant::query()
                    ->where('id', $variantId)
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->exists()
                : false;

            if (! $belongsToProduct) {
                $validator->errors()->add("variants.{$index}.id", 'The selected variant does not belong to this product.');
            }
        }
    }

    private function validateVariantIdentifiers(Validator $validator, string $field): void
    {
        $tenantId = $this->string('tenant_id')->toString();
        $seen = [];

        foreach ((array) $this->input('variants', []) as $index => $variant) {
            $value = strtoupper(trim((string) ($variant[$field] ?? '')));

            if ($value === '') {
                continue;
            }

            if (isset($seen[$value])) {
                $validator->errors()->add("variants.{$index}.{$field}", "Duplicate {$field} in the variant list.");
            }

            $seen[$value] = true;

            $exists = ProductVariant::query()
                ->where('tenant_id', $tenantId)
                ->where($field, $value)
                ->when($variant['id'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
                ->exists();

            if ($exists) {
                $validator->errors()->add("variants.{$index}.{$field}", "The {$field} has already been taken.");
            }
        }
    }

    private function validateOptions(Validator $validator): void
    {
        $seen = [];

        foreach ((array) $this->input('options', []) as $index => $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $values = collect(explode(',', (string) ($option['values'] ?? '')))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->values();

            if ($name === '' && $values->isEmpty()) {
                continue;
            }

            if ($name === '') {
                $validator->errors()->add("options.{$index}.name", 'Each option needs a name.');
            }

            if ($values->isEmpty()) {
                $validator->errors()->add("options.{$index}.values", 'Each option needs at least one value.');
            }

            $key = strtolower($name);

            if ($key !== '' && isset($seen[$key])) {
                $validator->errors()->add("options.{$index}.name", 'Duplicate option names are not allowed.');
            }

            $seen[$key] = true;
        }
    }

    private function validateAttributeValues(Validator $validator): void
    {
        $ids = collect((array) $this->input('attribute_value_ids', []))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $count = ProductAttributeValue::query()
            ->whereIn('id', $ids)
            ->whereHas('definition', fn ($query) => $query->where('tenant_id', $this->string('tenant_id')->toString()))
            ->count();

        if ($count !== $ids->count()) {
            $validator->errors()->add('attribute_value_ids', 'One or more selected attribute values are invalid.');
        }
    }

    private function validateTaxes(Validator $validator): void
    {
        if ($this->string('tax_behavior')->toString() !== TaxBehavior::Taxable->value) {
            return;
        }

        $ids = collect((array) $this->input('tax_ids', []))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $count = ProductTax::query()
            ->where('tenant_id', $this->string('tenant_id')->toString())
            ->whereIn('id', $ids)
            ->count();

        if ($count !== $ids->count()) {
            $validator->errors()->add('tax_ids', 'One or more selected taxes are invalid.');
        }
    }

    private function validateInlineAttributeValues(Validator $validator): void
    {
        $attributeIds = collect((array) $this->input('new_attribute_values', []))
            ->filter(fn (mixed $values): bool => trim((string) $values) !== '')
            ->keys()
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($attributeIds->isEmpty()) {
            return;
        }

        $count = ProductAttributeDefinition::query()
            ->where('tenant_id', $this->string('tenant_id')->toString())
            ->whereIn('id', $attributeIds)
            ->count();

        if ($count !== $attributeIds->count()) {
            $validator->errors()->add('new_attribute_values', 'One or more selected attributes are invalid.');
        }
    }

    private function validateInlineAttributes(Validator $validator): void
    {
        foreach ((array) $this->input('new_attributes', []) as $index => $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = trim((string) ($attribute['values'] ?? ''));

            if ($name === '' && $values === '') {
                continue;
            }

            if ($name === '') {
                $validator->errors()->add("new_attributes.{$index}.name", 'Enter the attribute name.');
            }

            if ($values === '') {
                $validator->errors()->add("new_attributes.{$index}.values", 'Enter at least one attribute value.');
            }
        }
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }

    private function cleanCommaList(mixed $value): string
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique(fn (string $item): string => strtolower($item))
            ->implode(', ');
    }
}
