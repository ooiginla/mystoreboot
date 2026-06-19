<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SupportTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
