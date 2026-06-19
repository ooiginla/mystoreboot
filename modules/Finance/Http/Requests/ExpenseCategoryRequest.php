<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $categoryId = $this->route('category')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'string', 'max:60', Rule::unique('finance_expense_categories', 'code')->where('tenant_id', $tenantId)->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
