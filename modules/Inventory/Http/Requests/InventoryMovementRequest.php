<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;

final class InventoryMovementRequest extends FormRequest
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
        $movementType = $this->string('movement_type')->toString();
        $hasItems = $this->boolean('multi_item') || is_array($this->input('items'));
        $requiresUnitCost = in_array($movementType, [
            InventoryMovementType::OpeningStock->value,
            InventoryMovementType::StockIn->value,
        ], true);
        $movementTypes = collect(InventoryMovementType::cases())
            ->reject(fn (InventoryMovementType $type): bool => in_array($type, [
                InventoryMovementType::TransferIn,
                InventoryMovementType::Returned,
            ], true))
            ->pluck('value')
            ->all();

        $rules = [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'multi_item' => ['nullable', 'boolean'],
            'inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'destination_inventory_location_id' => [
                Rule::requiredIf($movementType === InventoryMovementType::TransferOut->value),
                'nullable',
                'integer',
                'different:inventory_location_id',
                Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId),
            ],
            'product_variant_id' => [
                Rule::requiredIf(! $hasItems),
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'movement_type' => [Rule::requiredIf(! $hasItems), 'nullable', Rule::in($movementTypes)],
            'stock_condition' => ['required', Rule::in(array_column(StockCondition::cases(), 'value'))],
            'quantity' => [Rule::requiredIf(! $hasItems), 'nullable', 'numeric', 'gt:0', 'max:999999999'],
            'unit_id' => [
                'nullable', 'integer',
                Rule::exists('units_of_measure', 'id')->where('tenant_id', $tenantId),
            ],
            'total_cost' => [
                Rule::requiredIf($requiresUnitCost),
                'nullable',
                'numeric',
                Rule::when($requiresUnitCost, ['gt:0'], ['min:0']),
                'max:999999999',
            ],
            'batch_number' => ['nullable', 'string', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'reference_type' => [
                'nullable',
                'string',
                'max:80',
                Rule::notIn(['goods_receipt', 'sales_order', 'sales_return']),
            ],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
            'items' => [Rule::requiredIf($this->boolean('multi_item')), 'nullable', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.movement_type' => [
                Rule::requiredIf($movementType !== InventoryMovementType::TransferOut->value),
                'nullable',
                Rule::in($movementTypes),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_id' => [
                'nullable', 'integer',
                Rule::exists('units_of_measure', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.batch_number' => ['nullable', 'string', 'max:120'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];

        foreach ((array) $this->input('items', []) as $index => $item) {
            $itemType = is_array($item) ? ($item['movement_type'] ?? $movementType) : $movementType;
            $itemRequiresCost = in_array($itemType, [
                InventoryMovementType::OpeningStock->value,
                InventoryMovementType::StockIn->value,
            ], true);
            $rules["items.{$index}.total_cost"] = [
                Rule::requiredIf($itemRequiresCost),
                'nullable',
                'numeric',
                Rule::when($itemRequiresCost, ['gt:0'], ['min:0']),
                'max:999999999',
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $acceptsTotalCost = in_array($this->string('movement_type')->toString(), [
            InventoryMovementType::OpeningStock->value,
            InventoryMovementType::StockIn->value,
        ], true);

        $prepared = [
            'total_cost' => $acceptsTotalCost
                ? (is_string($this->input('total_cost')) ? str_replace(',', '', $this->input('total_cost')) : $this->input('total_cost'))
                : null,
        ];

        if (is_array($this->input('items'))) {
            $prepared['items'] = collect($this->input('items'))
                ->map(function (mixed $item): mixed {
                    if (! is_array($item)) {
                        return $item;
                    }

                    if (array_key_exists('total_cost', $item) && is_string($item['total_cost'])) {
                        $item['total_cost'] = str_replace(',', '', $item['total_cost']);
                    }

                    return $item;
                })
                ->all();
        }

        $this->merge($prepared);

        if ($this->string('movement_type')->toString() === InventoryMovementType::Damaged->value) {
            $this->merge(['stock_condition' => StockCondition::Damaged->value]);
        }

        if ($this->string('movement_type')->toString() === InventoryMovementType::Returned->value && ! $this->filled('stock_condition')) {
            $this->merge(['stock_condition' => StockCondition::Returned->value]);
        }
    }

    public function variant(): ?ProductVariant
    {
        return ProductVariant::query()->find($this->integer('product_variant_id'));
    }
}
