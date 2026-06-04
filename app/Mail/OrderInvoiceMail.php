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
    //Hỗ trợ đưa email vào hàng đợi gửi sau, để tránh làm chậm trang web
    //Giúp Laravel xử lý model Order an toàn khi email được queue hoặc serialize.
    use Queueable, SerializesModels;

    //Khởi tạo đối tượng Order
    public function __construct(public Order $order) {}

    //Tiêu đề email
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hóa đơn mua hàng #' . $this->order->order_code,
        );
    }

    //Nội dung email
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-invoice',
        );
    }

    //Đính kèm file
    public function attachments(): array
    {
        //Không đính kèm file
        return [];
    }
}
