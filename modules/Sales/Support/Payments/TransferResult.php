<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

/**
 * Provider-neutral result of initiating a payout/transfer.
 */
final readonly class TransferResult
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $ok,
        public string $status = self::STATUS_PENDING,
        public ?string $transferCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
