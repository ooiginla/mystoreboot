<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One or many item × location reorder rows. Values are decimals in the row's unit;
 * ownership of locations, items and units is checked in SaveReorderLevelsAction
 * with one query per table rather than one `exists` query per row.
 */
final class ReorderSettingRequest extends FormRequest
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
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'fragment' => ['nullable', 'alpha_dash', 'max:40'],
            'rows' => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.inventory_location_id' => ['required', 'integer'],
            'rows.*.product_variant_id' => ['required', 'integer'],
            'rows.*.unit_id' => ['nullable', 'integer'],
            'rows.*.reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'rows.*.reorder_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rows.*.reorder_level' => 'reorder level',
            'rows.*.reorder_quantity' => 'reorder quantity',
        ];
    }
}
