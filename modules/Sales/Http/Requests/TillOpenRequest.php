<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TillOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_float' => $this->cleanMoney($this->input('opening_float')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'opening_float' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
