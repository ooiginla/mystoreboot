<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Models\SalesOrder;

final class OnlineOrderPlacedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly OnlineStore $store,
        public readonly SalesOrder $order,
    ) {}

    public function envelope(): Envelope
    {
        $customerEmail = $this->order->customer?->email;

        return new Envelope(
            subject: "New order {$this->order->order_number} — {$this->store->store_name}",
            replyTo: filled($customerEmail)
                ? [new Address($customerEmail, $this->order->customer?->name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.online-order-placed',
        );
    }
}
