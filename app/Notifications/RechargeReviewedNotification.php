<?php

namespace App\Notifications;

use App\Models\Recharge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RechargeReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Recharge $recharge,
        public readonly bool $approved,
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
        $protocol = '#REC-'.str_pad((string) $this->recharge->id, 5, '0', STR_PAD_LEFT);
        $amount = number_format((float) ($this->recharge->approved_amount ?? $this->recharge->amount), 2, ',', '.');
        $statusText = $this->approved ? "aprovada (R$ {$amount})" : 'recusada';

        return (new MailMessage)
            ->subject("DigitalCard — Solicitação de Recarga {$protocol} {$statusText}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua solicitação de recarga {$protocol} foi {$statusText}.")
            ->when(! $this->approved && $this->recharge->admin_notes, fn (MailMessage $mail) => $mail->line("Motivo: {$this->recharge->admin_notes}"))
            ->action('Acessar Meu Cartão', route('card.show'))
            ->line($this->approved ? 'O saldo já está disponível para uso no seu cartão virtual.' : 'Em caso de dúvidas, verifique os dados do comprovante ou tente gerar um novo PIX.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $protocol = '#REC-'.str_pad((string) $this->recharge->id, 5, '0', STR_PAD_LEFT);
        $amount = number_format((float) ($this->recharge->approved_amount ?? $this->recharge->amount), 2, ',', '.');

        return [
            'type' => 'recharge_reviewed',
            'recharge_id' => (int) $this->recharge->id,
            'title' => "Recarga {$protocol}",
            'message' => $this->approved
                ? "Recarga aprovada! R$ {$amount} creditados na carteira."
                : 'Recarga recusada. Verifique os detalhes na carteira.',
            'url' => route('card.show'),
        ];
    }
}
