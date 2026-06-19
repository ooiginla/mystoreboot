<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

final class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('purchaseOrder')?->tenant_id;

        return [
            'receipt_number' => ['nullable', 'string', 'max:80', Rule::unique('goods_receipts', 'receipt_number')->where('tenant_id', $tenantId)],
            'received_at' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', Rule::exists('purchase_order_items', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity_received' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'items.*.batch_number' => ['nullable', 'string', 'max:120'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $purchaseOrder = $this->route('purchaseOrder')?->loadMissing('items');
            $items = collect((array) $this->input('items', []));
            $hasReceivedQuantity = false;

            foreach ($items as $index => $item) {
                $quantity = (int) ($item['quantity_received'] ?? 0);
                $poItem = $purchaseOrder?->items->firstWhere('id', (int) ($item['purchase_order_item_id'] ?? 0));

                if ($quantity > 0) {
                    $hasReceivedQuantity = true;
                }

                if ($poItem && $quantity > $poItem->quantity_pending) {
                    $validator->errors()->add("items.{$index}.quantity_received", 'Received quantity cannot be more than the pending quantity.');
                }
            }

            if (! $hasReceivedQuantity) {
                $validator->errors()->add('items', 'Enter at least one quantity to receive.');
            }
        });
    }
}
