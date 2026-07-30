<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesOrderRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $tenantId = $order?->tenant_id;

        return [
            'refund_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:80'],
            'sales_till_session_id' => [
                'nullable',
                'integer',
                Rule::exists('sales_till_sessions', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $order?->branch_id),
            ],
            'business_payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('business_payment_accounts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'),
            ],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
