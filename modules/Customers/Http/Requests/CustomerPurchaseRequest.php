<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => is_string($this->input('amount')) ? str_replace(',', '', $this->input('amount')) : $this->input('amount'),
            'loyalty_points_awarded' => $this->filled('loyalty_points_awarded') ? $this->integer('loyalty_points_awarded') : 0,
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['required', 'date'],
            'product_summary' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'loyalty_points_awarded' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
