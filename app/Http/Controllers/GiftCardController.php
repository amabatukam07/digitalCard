<?php

namespace App\Http\Controllers;

use App\Actions\Wallet\PurchaseGiftCardAction;
use App\Actions\Wallet\RedeemGiftCardAction;
use App\Services\CardPinGuard;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function __construct(
        private readonly PurchaseGiftCardAction $purchaseGiftCard,
        private readonly RedeemGiftCardAction $redeemGiftCard,
        private readonly CardPinGuard $pinGuard,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:25.00', 'max:5000.00'],
            'pin' => ['required', 'digits:4'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_email' => ['nullable', 'email', 'max:180'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $card = $user->virtualCard()->firstOrFail();

        $this->pinGuard->verify(
            user: $user,
            card: $card,
            pin: (string) $validated['pin'],
            field: 'pin',
            missingPinMessage: 'Configure um PIN de 4 dígitos no seu cartão antes de emitir um vale-presente.',
            invalidPinMessage: 'PIN incorreto. Confirme seu PIN de 4 dígitos para emitir o vale-presente.',
        );

        try {
            $giftCard = $this->purchaseGiftCard->execute(
                purchaser: $user,
                amount: (float) $validated['amount'],
                recipientName: $validated['recipient_name'] ?? null,
                recipientEmail: $validated['recipient_email'] ?? null,
                message: $validated['message'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $formattedAmount = 'R$ '.number_format((float) $giftCard->initial_amount, 2, ',', '.');

        return redirect()->route('card.show')->with(
            'success',
            "Vale-presente {$giftCard->code} ({$formattedAmount}) emitido! Compartilhe o código com quem desejar."
        );
    }

    public function redeem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:8', 'max:24'],
        ]);

        try {
            $giftCard = $this->redeemGiftCard->execute($request->user(), (string) $validated['code']);
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $formattedAmount = 'R$ '.number_format((float) $giftCard->initial_amount, 2, ',', '.');

        return redirect()->route('card.show')->with(
            'success',
            "Vale-presente {$giftCard->code} resgatado! {$formattedAmount} creditado instantaneamente no seu DigitalCard."
        );
    }
}
