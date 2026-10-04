<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Models\SalesOrder;

final class OnlineOrderProgressMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const PROCESSING = 'processing';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public function __construct(
        public readonly OnlineStore $store,
        public readonly SalesOrder $order,
        public readonly string $progress,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->progress) {
            self::PROCESSING => "We're preparing order {$this->order->order_number} — {$this->store->store_name}",
            self::OUT_FOR_DELIVERY => "Order {$this->order->order_number} is out for delivery — {$this->store->store_name}",
            default => "Order {$this->order->order_number} update — {$this->store->store_name}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.online-order-progress');
    }
}
