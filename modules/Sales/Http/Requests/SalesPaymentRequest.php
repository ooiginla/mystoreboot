<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesPaymentRequest extends FormRequest
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
        $tenantId = $this->route('order')?->tenant_id;

        return [
            'sales_till_session_id' => ['required', 'integer', Rule::exists('sales_till_sessions', 'id')->where('tenant_id', $tenantId)->where('status', 'open')],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:80'],
            'business_payment_account_id' => ['nullable', 'integer', Rule::exists('business_payment_accounts', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
