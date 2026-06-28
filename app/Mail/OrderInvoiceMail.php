<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Order $order) {}
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hóa đơn mua hàng #' . $this->order->order_code,
        );
    }
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-invoice',
        );
    }
    public function attachments(): array
    {
        return [];
    }
}

