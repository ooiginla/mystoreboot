<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;

final class SupportTicketRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(TicketType::values())],
            'category' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', Rule::in(TicketPriority::values())],
            'status' => ['required', Rule::in(TicketStatus::values())],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:4000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => $this->filled('customer_id') ? $this->input('customer_id') : null,
        ]);
    }
}
