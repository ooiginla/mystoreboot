<?php

declare(strict_types=1);

namespace Modules\Customers\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Enums\CustomerCreditPolicy;
use Modules\Customers\Models\Customer;
use Modules\Tenancy\Models\Tenant;

final class CustomerCreditService
{
    /**
     * @return array{enabled:bool, on_hold:bool, limit_minor:int|null, outstanding_minor:int, available_minor:int|null, terms_days:int|null, price_list_label:string}
     */
    public function profile(Customer $customer): array
    {
        $customer->loadMissing(['group.priceList', 'priceList']);
        $policy = $customer->credit_policy instanceof CustomerCreditPolicy
            ? $customer->credit_policy
            : CustomerCreditPolicy::from((string) $customer->credit_policy);
        $enabled = match ($policy) {
            CustomerCreditPolicy::Enabled => true,
            CustomerCreditPolicy::Disabled => false,
            CustomerCreditPolicy::Inherit => (bool) ($customer->group?->credit_enabled ?? false),
        };
        $limit = $customer->credit_limit_minor !== null
            ? (int) $customer->credit_limit_minor
            : ($customer->group?->credit_limit_minor !== null ? (int) $customer->group->credit_limit_minor : null);
        $terms = $customer->credit_terms_days !== null
            ? (int) $customer->credit_terms_days
            : ($customer->group?->credit_terms_days !== null ? (int) $customer->group->credit_terms_days : null);
        $outstanding = max(0, (int) $customer->account_balance_minor);

        return [
            'enabled' => $enabled,
            'on_hold' => (bool) $customer->credit_hold,
            'limit_minor' => $limit,
            'outstanding_minor' => $outstanding,
            'available_minor' => $limit === null ? null : max(0, $limit - $outstanding),
            'terms_days' => $terms,
            'price_list_label' => $customer->priceList?->name ?? $customer->group?->priceList?->name ?? 'Retail',
        ];
    }

    /**
     * Validate a new receivable against the customer's effective credit policy.
     *
     * @return array{profile:array<string, mixed>, overridden:bool}
     */
    public function authorizeNewCredit(
        Tenant $tenant,
        Customer $customer,
        int $newCreditMinor,
        User $actor,
        ?string $overrideReason = null,
    ): array {
        $profile = $this->profile($customer);

        if ($newCreditMinor <= 0) {
            return ['profile' => $profile, 'overridden' => false];
        }

        if (! $profile['enabled']) {
            throw ValidationException::withMessages([
                'is_credit_sale' => 'Credit sales are not enabled for this customer.',
            ]);
        }

        if ($profile['on_hold']) {
            throw ValidationException::withMessages([
                'is_credit_sale' => 'This customer is on credit hold. Remove the hold before creating another credit sale.',
            ]);
        }

        $limit = $profile['limit_minor'];
        $wouldExceed = $limit !== null
            && $limit < $profile['outstanding_minor'] + $newCreditMinor;

        if (! $wouldExceed) {
            return ['profile' => $profile, 'overridden' => false];
        }

        $canOverride = $actor->is_platform_admin || $actor->hasPermission($tenant, 'sales.credit.override');
        if (! $canOverride) {
            throw ValidationException::withMessages([
                'amount_paid' => 'This sale would exceed the customer’s available credit. Collect more payment or ask an authorised manager to override it.',
            ]);
        }

        if (blank($overrideReason)) {
            throw ValidationException::withMessages([
                'credit_override_reason' => 'Enter a reason to override the customer’s credit limit.',
            ]);
        }

        return ['profile' => $profile, 'overridden' => true];
    }
}
