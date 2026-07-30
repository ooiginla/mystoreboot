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

final class OnlineOrderConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly OnlineStore $store,
        public readonly SalesOrder $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order confirmation {$this->order->order_number} — {$this->store->store_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.online-order-confirmation',
        );
    }
}
