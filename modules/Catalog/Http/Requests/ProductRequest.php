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
use Modules\Catalog\Models\ProductAttributeValue;
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
            'discount_price' => $this->cleanMoney($this->input('discount_price')),
            'variants' => collect((array) $this->input('variants', []))
                ->map(function (array $variant): array {
                    $variant['sku'] = isset($variant['sku']) && trim((string) $variant['sku']) !== '' ? strtoupper((string) $variant['sku']) : null;
                    $variant['barcode'] = isset($variant['barcode']) && trim((string) $variant['barcode']) !== '' ? strtoupper((string) $variant['barcode']) : null;
                    $variant['selling_price'] = $this->cleanMoney($variant['selling_price'] ?? null);
                    $variant['cost_price'] = $this->cleanMoney($variant['cost_price'] ?? null);
                    $variant['discount_price'] = $this->cleanMoney($variant['discount_price'] ?? null);

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
            'discount_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_behavior' => ['required', Rule::in(array_column(TaxBehavior::cases(), 'value'))],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(ProductStatus::values())],
            'image' => ['nullable', 'image', 'max:2048'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')->where('tenant_id', $this->string('tenant_id')->toString())],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer'],
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
            'variants.*.discount_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
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
            $this->validateAttributeValues($validator);
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

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
