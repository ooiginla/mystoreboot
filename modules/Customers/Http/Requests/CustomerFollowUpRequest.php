<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\FollowUpStatus;

final class CustomerFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'subject' => ['required', 'string', 'max:180'],
            'due_date' => ['required', 'date'],
            'channel' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in(FollowUpStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
