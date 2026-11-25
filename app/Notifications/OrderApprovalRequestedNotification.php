<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $requesterName,
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
        $total = number_format((float) $this->order->total_amount, 2, ',', '.');

        return (new MailMessage)
            ->subject("NexoSupri — Pedido {$orderCode} aguardando aprovação")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O solicitante **{$this->requesterName}** registrou o pedido {$orderCode} no valor de **R$ {$total}** ({$total} créditos).")
            ->action('Revisar e aprovar pedido', route('orders.show', $this->order))
            ->line('Os créditos só serão debitados após sua aprovação como gestor da empresa.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderCode = '#PED-'.str_pad((string) $this->order->id, 6, '0', STR_PAD_LEFT);
        $total = number_format((float) $this->order->total_amount, 2, ',', '.');

        return [
            'type' => 'order_approval_requested',
            'order_id' => (int) $this->order->id,
            'title' => "Pedido {$orderCode} aguardando aprovação",
            'message' => "{$this->requesterName} solicitou compra de {$total} créditos.",
            'url' => route('orders.show', $this->order),
        ];
    }
}
