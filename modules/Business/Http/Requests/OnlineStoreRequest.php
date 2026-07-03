<?php

declare(strict_types=1);

namespace Modules\Business\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Business\Models\OnlineStore;
use Modules\Tenancy\Models\Tenant;

final class OnlineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paymentMethods = collect((array) $this->input('payment_methods', []))
            ->map(fn (mixed $method): string => (string) $method)
            ->values()
            ->all();
        $paystackMethod = (string) $this->input('paystack_method', 'none');

        $paymentMethods = array_values(array_filter(
            $paymentMethods,
            fn (string $method): bool => ! in_array($method, ['storeboot_paystack', 'self_hosted_paystack'], true)
        ));

        if (in_array($paystackMethod, ['storeboot_paystack', 'self_hosted_paystack'], true)) {
            $paymentMethods[] = $paystackMethod;
        }

        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'payment_methods' => $paymentMethods,
            'paystack_method' => $paystackMethod,
            'maintenance_mode' => $this->boolean('maintenance_mode'),
            'category_ids' => array_values((array) $this->input('category_ids', [])),
            'shipping_options' => array_values((array) $this->input('shipping_options', [])),
            'faqs' => array_values((array) $this->input('faqs', [])),
            'slides' => array_values((array) $this->input('slides', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $onlineStoreId = OnlineStore::query()->where('tenant_id', $tenantId)->value('id');

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'online_store_section' => ['nullable', Rule::in([
                'online-store-basics',
                'online-store-contact',
                'online-store-theme',
                'online-store-payments',
                'online-store-shipping',
                'online-store-socials',
                'online-store-pages',
                'online-store-faq',
            ])],
            'username' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('online_stores', 'username')->ignore($onlineStoreId)],
            'store_name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'hero_image' => ['nullable', 'image', 'max:10240'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'site_email' => ['nullable', 'email:rfc', 'max:160'],
            'store_phone' => ['nullable', 'string', 'max:40'],
            'store_whatsapp' => ['nullable', 'string', 'max:40'],
            'hero_image_text' => ['nullable', 'string', 'max:120'],
            'hero_image_description' => ['nullable', 'string', 'max:255'],
            'hero_image_tag' => ['nullable', 'string', 'max:80'],
            'slides' => ['array', 'max:12'],
            'slides.*.image' => ['nullable', 'image', 'max:10240'],
            'slides.*.existing_image_path' => ['nullable', 'string', 'max:255'],
            'slides.*.hero_image_text' => ['nullable', 'string', 'max:120'],
            'slides.*.hero_image_description' => ['nullable', 'string', 'max:255'],
            'slides.*.hero_image_tag' => ['nullable', 'string', 'max:80'],
            'announcement' => ['nullable', 'string', 'max:1000'],
            'theme_primary_color' => ['required', 'string', 'max:20'],
            'theme_secondary_color' => ['required', 'string', 'max:20'],
            'fulfilment_branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId)],
            'payment_methods' => ['array'],
            'payment_methods.*' => ['string', Rule::in(['pay_on_delivery', 'storeboot_paystack', 'self_hosted_paystack', 'bank_account', 'place_order'])],
            'paystack_method' => ['required', Rule::in(['none', 'storeboot_paystack', 'self_hosted_paystack'])],
            'paystack.public_key' => ['nullable', 'required_if:paystack_method,self_hosted_paystack', 'string', 'max:255'],
            'paystack.private_key' => ['nullable', 'required_if:paystack_method,self_hosted_paystack', 'string', 'max:255'],
            'settlement_bank_account.bank_name' => ['nullable', 'string', 'max:140'],
            'settlement_bank_account.account_number' => ['nullable', 'string', 'max:80'],
            'settlement_bank_account.account_name' => ['nullable', 'string', 'max:160'],
            'bank_account_key' => ['nullable', 'string', 'size:40'],
            'maintenance_mode' => ['boolean'],
            'shipping_options' => ['array', 'max:30'],
            'shipping_options.*.location' => ['nullable', 'string', 'max:160'],
            'shipping_options.*.description' => ['nullable', 'string', 'max:255'],
            'shipping_options.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'socials.instagram' => ['nullable', 'string', 'max:255'],
            'socials.tiktok' => ['nullable', 'string', 'max:255'],
            'socials.facebook' => ['nullable', 'string', 'max:255'],
            'socials.twitter' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.whatsapp' => ['nullable', 'string', 'max:80'],
            'pages.about_us' => ['nullable', 'string', 'max:5000'],
            'pages.terms_of_use' => ['nullable', 'string', 'max:5000'],
            'pages.return_policy' => ['nullable', 'string', 'max:5000'],
            'pages.privacy_policy' => ['nullable', 'string', 'max:5000'],
            'pages.shipping_information' => ['nullable', 'string', 'max:5000'],
            'faqs' => ['array', 'max:30'],
            'faqs.*.question' => ['nullable', 'string', 'max:255'],
            'faqs.*.answer' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! in_array('bank_account', (array) $this->input('payment_methods', []), true)) {
                return;
            }

            $selectedKey = (string) $this->input('bank_account_key', '');
            $tenant = Tenant::query()->find($this->string('tenant_id')->toString());
            $validKeys = collect($tenant?->settings['bank_details'] ?? [])
                ->filter(fn ($account): bool => is_array($account) && ($account['status'] ?? 'active') === 'active')
                ->map(fn (array $account): array => [
                    'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                    'account_name' => trim((string) ($account['account_name'] ?? '')),
                    'account_number' => trim((string) ($account['account_number'] ?? '')),
                ])
                ->filter(fn (array $account): bool => $account['bank_name'] !== '' && $account['account_number'] !== '')
                ->map(fn (array $account): string => sha1(implode('|', [$account['bank_name'], $account['account_name'], $account['account_number']])));

            if ($selectedKey === '' || ! $validKeys->contains($selectedKey)) {
                $validator->errors()->add('bank_account_key', 'Select an active business bank account for Pay via Transfer.');
            }
        });
    }
}
