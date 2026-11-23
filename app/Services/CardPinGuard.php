<?php

namespace App\Services;

use App\Http\Controllers\VirtualCardController;
use App\Models\User;
use App\Models\VirtualCard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CardPinGuard
{
    public function verify(
        User $user,
        VirtualCard $card,
        string $pin,
        string $field = 'pin',
        string $missingPinMessage = 'Configure um PIN de 4 dígitos no seu cartão virtual antes de continuar.',
        string $invalidPinMessage = 'PIN incorreto. Informe o PIN de 4 dígitos do seu cartão virtual.',
    ): void {
        if (empty($card->security_pin)) {
            throw ValidationException::withMessages([$field => $missingPinMessage]);
        }

        $throttleKey = VirtualCardController::pinThrottleKey($user->id);

        if (RateLimiter::tooManyAttempts($throttleKey, VirtualCardController::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            throw ValidationException::withMessages([
                $field => "Muitas tentativas incorretas de PIN. Aguarde {$minutes} minuto(s) ({$seconds}s).",
            ]);
        }

        if (! Hash::check($pin, $card->security_pin)) {
            RateLimiter::hit($throttleKey, VirtualCardController::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([$field => $invalidPinMessage]);
        }

        RateLimiter::clear($throttleKey);
    }
}
