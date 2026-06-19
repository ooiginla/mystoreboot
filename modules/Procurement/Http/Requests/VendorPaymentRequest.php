<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Procurement\Models\PurchaseOrder;

final class VendorPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => is_string($this->input('amount')) ? str_replace(',', '', $this->input('amount')) : $this->input('amount'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('purchase_order_id')) {
                return;
            }

            $purchaseOrder = PurchaseOrder::query()
                ->where('tenant_id', $this->string('tenant_id')->toString())
                ->find($this->integer('purchase_order_id'));

            if (! $purchaseOrder) {
                return;
            }

            if ($purchaseOrder->vendor_id !== $this->integer('vendor_id')) {
                $validator->errors()->add('vendor_id', 'Vendor must match the selected purchase order.');
            }

            $amountMinor = (int) round(((float) $this->input('amount')) * 100);

            if ($amountMinor > $purchaseOrder->balance_minor) {
                $validator->errors()->add('amount', 'Payment amount cannot be more than the outstanding balance.');
            }
        });
    }
}
