<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $newStatus,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orderCode = '#PED-'.str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT);

        return (new MailMessage)
            ->subject("DigitalCard — Pedido {$orderCode}: {$this->newStatus}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O status do seu pedido {$orderCode} foi atualizado para: **{$this->newStatus}**.")
            ->when($this->order->tracking_code, fn (MailMessage $mail) => $mail->line("Código de rastreio: {$this->order->tracking_code}"))
            ->action('Acompanhar Pedido', route('orders.show', $this->order))
            ->line('Agradecemos por comprar com a DigitalCard.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderCode = '#PED-'.str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT);

        return [
            'type' => 'order_status',
            'order_id' => (int) $this->order->id,
            'title' => "Pedido {$orderCode}",
            'message' => "Status atualizado para: {$this->newStatus}.",
            'url' => route('orders.show', $this->order),
        ];
    }
}
