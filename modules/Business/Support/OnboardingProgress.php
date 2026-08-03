<?php

declare(strict_types=1);

namespace Modules\Business\Support;

use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Business\Models\OnlineStore;
use Modules\Tenancy\Models\Tenant;

/**
 * Computes an onboarding checklist for a tenant: how far along they are on
 * getting their business profile and online store ready, and where the next
 * unfinished step lives. Rendered as a nudge on the dashboard.
 */
final class OnboardingProgress
{
    /**
     * @param  list<array{key: string, label: string, done: bool, href: string}>  $steps
     */
    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly string $icon,
        public readonly array $steps,
    ) {}

    /**
     * Business profile readiness: fill in the business details and add at least
     * one payment account so money can be recorded against real accounts.
     */
    public static function businessProfile(Tenant $tenant): self
    {
        $tid = $tenant->id;
        $profileUrl = route('admin.business.index', ['tenant' => $tid]).'#business-profile';
        $paymentUrl = route('admin.business.index', ['tenant' => $tid]).'#payment-accounts';

        $profileDone = filled($tenant->business_type)
            && filled($tenant->address)
            && (filled($tenant->email) || filled($tenant->phone));

        $hasPaymentAccount = BusinessPaymentAccount::query()
            ->where('tenant_id', $tid)
            ->exists();

        return new self(
            key: 'business-profile',
            title: 'Complete your business profile',
            description: 'Set up your organization so sales, tax, and money are recorded correctly.',
            icon: 'building',
            steps: [
                ['key' => 'profile', 'label' => 'Add your business details', 'done' => $profileDone, 'href' => $profileUrl],
                ['key' => 'payment_account', 'label' => 'Set up a payment account', 'done' => $hasPaymentAccount, 'href' => $paymentUrl],
            ],
        );
    }

    /**
     * Online store readiness across the five setup tabs:
     * Basic, Contact, Theme, Payment, and Shipping.
     */
    public static function storeSetup(Tenant $tenant): self
    {
        $tid = $tenant->id;
        $store = OnlineStore::query()->where('tenant_id', $tid)->first();

        $section = static fn (string $tab): string => route('admin.business.online-store.index', [
            'tenant' => $tid,
            'online_store_section' => $tab,
        ]).'#online-store';

        $basicsDone = $store !== null && filled($store->store_name) && filled($store->username);

        $contactDone = $store !== null
            && filled($store->address)
            && (filled($store->site_email) || filled($store->store_phone) || filled($store->store_whatsapp));

        $themeDone = $store !== null && (
            filled($store->hero_image_path)
            || collect($store->slides ?? [])->contains(
                fn ($slide): bool => filled($slide['image_path'] ?? $slide['existing_image_path'] ?? null)
            )
        );

        $paymentDone = $store !== null && collect($store->payment_methods ?? [])
            ->filter(fn ($method): bool => filled($method))
            ->isNotEmpty();

        $shippingDone = $store !== null && collect($store->shipping_options ?? [])
            ->contains(fn ($option): bool => filled($option['location'] ?? null));

        return new self(
            key: 'store-setup',
            title: 'Set up your online store',
            description: 'Get your storefront ready to take orders across all five setup steps.',
            icon: 'store',
            steps: [
                ['key' => 'basic', 'label' => 'Store basics', 'done' => $basicsDone, 'href' => $section('online-store-basics')],
                ['key' => 'contact', 'label' => 'Contact details', 'done' => $contactDone, 'href' => $section('online-store-contact')],
                ['key' => 'theme', 'label' => 'Theme & banners', 'done' => $themeDone, 'href' => $section('online-store-theme')],
                ['key' => 'payment', 'label' => 'Payment methods', 'done' => $paymentDone, 'href' => $section('online-store-payments')],
                ['key' => 'shipping', 'label' => 'Shipping options', 'done' => $shippingDone, 'href' => $section('online-store-shipping')],
            ],
        );
    }

    public function completed(): int
    {
        return count(array_filter($this->steps, fn (array $step): bool => $step['done']));
    }

    public function total(): int
    {
        return count($this->steps);
    }

    public function isComplete(): bool
    {
        return $this->completed() === $this->total();
    }

    public function percent(): int
    {
        return $this->total() === 0 ? 0 : (int) round($this->completed() / $this->total() * 100);
    }

    /**
     * The first unfinished step — where the "continue setup" button should go.
     *
     * @return array{key: string, label: string, done: bool, href: string}|null
     */
    public function nextStep(): ?array
    {
        foreach ($this->steps as $step) {
            if (! $step['done']) {
                return $step;
            }
        }

        return null;
    }
}
