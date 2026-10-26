<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerCreditPolicy;
use Modules\Customers\Enums\CustomerStatus;

final class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\s+/', '', (string) $this->input('phone')),
            'loyalty_points' => $this->filled('loyalty_points') ? $this->integer('loyalty_points') : 0,
            'account_balance' => is_string($this->input('account_balance')) ? str_replace(',', '', $this->input('account_balance')) : $this->input('account_balance'),
            'credit_limit' => is_string($this->input('credit_limit')) ? str_replace(',', '', $this->input('credit_limit')) : $this->input('credit_limit'),
            'credit_hold' => $this->boolean('credit_hold'),
            'credit_policy' => $this->input('credit_policy', $this->route('customer')?->credit_policy?->value ?? CustomerCreditPolicy::Inherit->value),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $customerId = $this->route('customer')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('tenant_id', $tenantId)],
            'customer_price_list_id' => ['nullable', 'integer', Rule::exists('customer_price_lists', 'id')->where('tenant_id', $tenantId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:60', Rule::unique('customers', 'phone')->where('tenant_id', $tenantId)->ignore($customerId)],
            'email' => ['nullable', 'email', 'max:160'],
            'birthday' => ['nullable', 'date'],
            'anniversary' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(CustomerStatus::values())],
            'loyalty_points' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'credit_policy' => ['required', Rule::enum(CustomerCreditPolicy::class)],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'credit_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'credit_hold' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
