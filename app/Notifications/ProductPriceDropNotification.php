<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductPriceDropNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly float $targetPrice,
        public readonly float $currentPrice,
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
        $formattedCurrent = 'R$ '.number_format($this->currentPrice, 2, ',', '.');
        $formattedTarget = 'R$ '.number_format($this->targetPrice, 2, ',', '.');

        return (new MailMessage)
            ->subject("DigitalCard — Alerta de preço: {$this->product->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O produto **{$this->product->name}** atingiu o preço desejado no seu alerta!")
            ->line("Preço atual: **{$formattedCurrent}** (sua meta: {$formattedTarget}).")
            ->action('Aproveitar oferta agora', route('catalog.product', $this->product->slug))
            ->line('Você pode pagar com seu saldo DigitalCard e garantir cashback na entrega.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $formattedCurrent = 'R$ '.number_format($this->currentPrice, 2, ',', '.');

        return [
            'type' => 'price_drop',
            'product_id' => (int) $this->product->id,
            'title' => 'Baixa de preço no seu alerta',
            'message' => "{$this->product->name} agora custa {$formattedCurrent}.",
            'url' => route('catalog.product', $this->product->slug),
        ];
    }
}
