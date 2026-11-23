<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductQuestionAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly ProductQuestion $question,
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
        return (new MailMessage)
            ->subject("DigitalCard — Sua pergunta sobre {$this->product->name} foi respondida")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua dúvida sobre **{$this->product->name}** recebeu uma resposta da equipe DigitalCard:")
            ->line('Pergunta: "'.$this->question->question.'"')
            ->line('Resposta: "'.$this->question->answer.'"')
            ->action('Ver resposta no produto', route('catalog.product', $this->product->slug).'#perguntas')
            ->line('Conte com a DigitalCard para tirar qualquer dúvida antes da sua compra.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_question_answered',
            'product_id' => (int) $this->product->id,
            'question_id' => (int) $this->question->id,
            'title' => 'Pergunta respondida',
            'message' => "Respondemos sua pergunta sobre {$this->product->name}.",
            'url' => route('catalog.product', $this->product->slug).'#perguntas',
        ];
    }
}
