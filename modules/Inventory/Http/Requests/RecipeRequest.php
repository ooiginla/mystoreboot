<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecipeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'output_product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'yield_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'yield_unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'prep_station_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'items.*.wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
