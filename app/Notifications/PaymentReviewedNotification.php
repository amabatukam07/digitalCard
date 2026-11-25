<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
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
        $protocol = '#PAG-'.str_pad((string) $this->payment->id, 5, '0', STR_PAD_LEFT);
        $amount = number_format((float) ($this->payment->approved_amount ?? $this->payment->amount), 2, ',', '.');
        $statusText = $this->approved ? "aprovado ({$amount} créditos)" : 'recusado';

        return (new MailMessage)
            ->subject("NexoSupri — Pagamento {$protocol} {$statusText}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O pagamento {$protocol} da sua empresa foi {$statusText}.")
            ->when(! $this->approved && $this->payment->admin_notes, fn (MailMessage $mail) => $mail->line("Motivo: {$this->payment->admin_notes}"))
            ->action('Acessar Carteira de Créditos', route('wallet.show'))
            ->line($this->approved ? 'Os créditos já estão disponíveis na carteira da empresa (1 crédito = R$ 1,00).' : 'Verifique os dados do comprovante ou gere uma nova cobrança PIX.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $protocol = '#PAG-'.str_pad((string) $this->payment->id, 5, '0', STR_PAD_LEFT);
        $amount = number_format((float) ($this->payment->approved_amount ?? $this->payment->amount), 2, ',', '.');

        return [
            'type' => 'payment_reviewed',
            'payment_id' => (int) $this->payment->id,
            'title' => "Pagamento {$protocol}",
            'message' => $this->approved
                ? "Pagamento confirmado! {$amount} créditos liberados na carteira da empresa."
                : 'Pagamento recusado. Verifique os detalhes na carteira.',
            'url' => route('wallet.show'),
        ];
    }
}
