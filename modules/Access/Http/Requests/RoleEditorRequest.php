<?php

declare(strict_types=1);

namespace Modules\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Access\Support\PermissionCatalogue;

final class RoleEditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modules = array_keys(PermissionCatalogue::modules());
        $sensitive = array_keys(array_filter(
            PermissionCatalogue::definitions(),
            static fn (array $d): bool => $d['sensitive'],
        ));

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'levels' => ['array'],
            'levels.*' => [Rule::in(['none', 'view', 'operate', 'manage'])],
            'sensitive' => ['array'],
            'sensitive.*' => [Rule::in($sensitive)],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function levels(): array
    {
        return array_intersect_key(
            (array) $this->input('levels', []),
            array_flip(array_keys(PermissionCatalogue::modules())),
        );
    }
}
