<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

use InvalidArgumentException;

/**
 * Resolves a {@see PaymentGateway} by provider name. Register new providers here (and add
 * their implementation) — everything else stays provider-neutral.
 */
final class PaymentGatewayManager
{
    public function for(string $provider): PaymentGateway
    {
        return match (strtolower(trim($provider))) {
            'paystack' => new PaystackGateway,
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$provider}]."),
        };
    }

    /** The platform's default gateway (configurable, so a swap is a one-line config change). */
    public function default(): PaymentGateway
    {
        return $this->for((string) config('services.payments.default', 'paystack'));
    }
}
