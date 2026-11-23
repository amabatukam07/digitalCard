<?php

namespace App\Notifications;

use App\Models\CardTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletTransferReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $senderName,
        public readonly float $amount,
        public readonly CardTransaction $transaction,
        public readonly ?string $note = null,
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
        $formatted = 'R$ '.number_format($this->amount, 2, ',', '.');

        return (new MailMessage)
            ->subject("DigitalCard — Você recebeu {$formatted} de {$this->senderName}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("**{$this->senderName}** enviou uma transferência instantânea de **{$formatted}** para o seu DigitalCard.")
            ->when($this->note, fn (MailMessage $mail) => $mail->line("Mensagem: \"{$this->note}\""))
            ->action('Ver extrato na carteira', route('card.show'))
            ->line('O saldo já está disponível para compras com cashback.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $formatted = 'R$ '.number_format($this->amount, 2, ',', '.');

        return [
            'type' => 'wallet_transfer',
            'transaction_id' => (int) $this->transaction->id,
            'title' => "Transferência recebida ({$formatted})",
            'message' => "{$this->senderName} enviou {$formatted} para a sua carteira DigitalCard.",
            'url' => route('card.show'),
        ];
    }
}
