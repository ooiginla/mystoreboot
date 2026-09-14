<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductionRequest extends FormRequest
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
            'tenant' => ['nullable', 'string'],
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
            'source_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'output_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'actual_yield_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.planned_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
        ];
    }
}
