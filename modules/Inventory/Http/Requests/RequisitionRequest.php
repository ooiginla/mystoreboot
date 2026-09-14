<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequisitionRequest extends FormRequest
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
            'source_location_id' => ['required', 'integer', 'exists:inventory_locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
        ];
    }
}
