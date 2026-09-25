<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Reseller\Enums\PricingMode;

final class ResellerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $percentage = round((float) $this->input('percentage_markup', 0), 2);

        $this->merge([
            'percentage_markup_basis_points' => (int) round($percentage * 100),
            'fixed_markup_minor' => (int) round((float) $this->input('fixed_markup', 0) * 100),
            'auto_publish_products' => $this->boolean('auto_publish_products'),
            'show_source_store' => $this->boolean('show_source_store'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'percentage_markup_basis_points' => ['required', 'integer', 'between:0,50000'],
            'fixed_markup_minor' => ['required', 'integer', 'min:0'],
            'auto_publish_products' => ['required', 'boolean'],
            'scan_frequency' => ['required', Rule::in(['daily', 'twice_daily'])],
            'show_source_store' => ['required', 'boolean'],
            'stale_after_hours' => ['required', 'integer', 'between:1,720'],
            'hide_after_missing_scans' => ['required', 'integer', 'between:1,20'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $mode = PricingMode::tryFrom($this->string('pricing_mode')->toString());
            $percentage = $this->integer('percentage_markup_basis_points');
            $fixed = $this->integer('fixed_markup_minor');

            if (in_array($mode, [PricingMode::Percentage, PricingMode::Combined], true) && $percentage < 1) {
                $validator->errors()->add('percentage_markup', 'Enter a percentage greater than zero.');
            }

            if (in_array($mode, [PricingMode::Fixed, PricingMode::Combined], true) && $fixed < 1) {
                $validator->errors()->add('fixed_markup', 'Enter a fixed addition greater than zero.');
            }
        }];
    }
}
