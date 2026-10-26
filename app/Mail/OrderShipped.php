<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued: the staff member's click does not wait on the mail server, and a mail server outage does not
 * fail the shipping. Sent after the transaction commits, so a rolled-back shipping sends nothing.
 */
class OrderShipped extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order {$this->order->number} is on its way");
    }

    public function content(): Content
    {
        $this->order->loadMissing(['customer', 'items.product']);

        return new Content(view: 'mail.order-shipped');
    }
}
