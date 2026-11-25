<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Company $company,
        public readonly float $currentBalance,
        public readonly float $threshold,
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
        $bal = number_format($this->currentBalance, 2, ',', '.');
        $thr = number_format($this->threshold, 2, ',', '.');

        return (new MailMessage)
            ->subject("NexoSupri — Alerta de saldo baixo ({$this->company->trade_name})")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O saldo da carteira de créditos da empresa **{$this->company->trade_name}** atingiu **{$bal} créditos** (limite de alerta: {$thr} créditos).")
            ->action('Contratar nova recarga de créditos', route('wallet.show'))
            ->line('Antecipe a contratação de um plano para evitar interrupções no abastecimento.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $bal = number_format($this->currentBalance, 2, ',', '.');

        return [
            'type' => 'low_balance',
            'company_id' => (int) $this->company->id,
            'title' => 'Saldo de créditos baixo',
            'message' => "{$this->company->trade_name} está com {$bal} créditos disponíveis. Sugerimos contratar nova carga.",
            'url' => route('wallet.show'),
        ];
    }
}
