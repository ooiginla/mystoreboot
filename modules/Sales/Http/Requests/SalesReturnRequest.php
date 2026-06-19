<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('order')?->tenant_id;

        return [
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['required', 'integer', Rule::exists('sales_order_items', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
