<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TillMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->cleanMoney($this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', Rule::in(['cash_in', 'cash_out', 'petty_cash_withdrawal', 'cash_deposit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
