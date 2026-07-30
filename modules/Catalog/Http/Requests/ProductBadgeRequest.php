<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProductBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'background_color' => strtolower((string) ($this->input('background_color') ?: '#111827')),
            'text_color' => strtolower((string) ($this->input('text_color') ?: '#ffffff')),
            'is_visible' => $this->boolean('is_visible'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $badgeId = $this->route('badge')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:40'],
            'slug' => [
                'required',
                'string',
                'max:60',
                Rule::unique('product_badges', 'slug')
                    ->where('tenant_id', $this->string('tenant_id')->toString())
                    ->ignore($badgeId),
            ],
            'background_color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'text_color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'is_visible' => ['boolean'],
        ];
    }
}
