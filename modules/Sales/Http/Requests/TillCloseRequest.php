<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TillCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'actuals' => collect((array) $this->input('actuals', []))
                ->map(fn (mixed $value): mixed => is_string($value) ? str_replace(',', '', $value) : $value)
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'actuals' => ['required', 'array'],
            'actuals.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
