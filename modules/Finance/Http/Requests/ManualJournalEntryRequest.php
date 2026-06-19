<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ManualJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'lines' => collect((array) $this->input('lines', []))
                ->map(function (array $line): array {
                    $line['debit'] = $this->cleanMoney($line['debit'] ?? null);
                    $line['credit'] = $this->cleanMoney($line['credit'] ?? null);

                    return $line;
                })
                ->filter(fn (array $line): bool => trim((string) ($line['account_code'] ?? '')) !== '' || (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'entry_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2', 'max:20'],
            'lines.*.account_code' => ['required', 'string', Rule::exists('finance_accounts', 'code')->where('tenant_id', $tenantId)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $debits = 0;
            $credits = 0;

            foreach ((array) $this->input('lines', []) as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'A journal line cannot have both debit and credit.');
                }

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'Enter a debit or credit amount.');
                }

                $debits += (int) round($debit * 100);
                $credits += (int) round($credit * 100);
            }

            if ($debits !== $credits) {
                $validator->errors()->add('lines', 'Journal debits and credits must balance.');
            }
        });
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
