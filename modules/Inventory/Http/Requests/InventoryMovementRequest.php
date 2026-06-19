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
        $movementTypes = collect(InventoryMovementType::cases())
            ->reject(fn (InventoryMovementType $type): bool => $type === InventoryMovementType::TransferIn)
            ->pluck('value')
            ->all();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'destination_inventory_location_id' => [
                Rule::requiredIf($this->string('movement_type')->toString() === InventoryMovementType::TransferOut->value),
                'nullable',
                'integer',
                'different:inventory_location_id',
                Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId),
            ],
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'movement_type' => ['required', Rule::in($movementTypes)],
            'stock_condition' => ['required', Rule::in(array_column(StockCondition::cases(), 'value'))],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'batch_number' => ['nullable', 'string', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_cost' => is_string($this->input('unit_cost')) ? str_replace(',', '', $this->input('unit_cost')) : $this->input('unit_cost'),
        ]);

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
