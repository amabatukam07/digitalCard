<?php

namespace App\Http\Controllers;

use App\Actions\Wallet\DepositSavingsGoalAction;
use App\Actions\Wallet\WithdrawSavingsGoalAction;
use App\Models\Product;
use App\Models\SavingsGoal;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavingsGoalController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'target_amount' => ['required', 'numeric', 'min:10.00', 'max:100000.00'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        $user = $request->user();
        if ($user->savingsGoals()->count() >= 15) {
            return back()->with('error', 'Você atingiu o limite de 15 metas ativas no Cofrinho DigitalCard.');
        }

        $productId = ! empty($validated['product_id']) ? (int) $validated['product_id'] : null;
        if ($productId !== null && ! Product::whereKey($productId)->where('is_active', true)->exists()) {
            $productId = null;
        }

        $goal = SavingsGoal::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'name' => trim((string) $validated['name']),
            'target_amount' => round((float) $validated['target_amount'], 2),
            'current_amount' => 0.00,
        ]);

        return redirect()->route('card.show')->with(
            'success',
            "Meta \"{$goal->name}\" criada no Cofrinho DigitalCard! Deposite qualquer valor quando quiser."
        );
    }

    public function deposit(
        Request $request,
        SavingsGoal $goal,
        DepositSavingsGoalAction $depositAction,
    ): RedirectResponse {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1.00', 'max:50000.00'],
        ]);

        try {
            $updated = $depositAction->execute($request->user(), $goal, (float) $validated['amount']);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $formatted = 'R$ '.number_format((float) $validated['amount'], 2, ',', '.');
        $doneNotice = $updated->completed_at ? ' Parabéns, meta 100% concluída!' : '';

        return redirect()->route('card.show')->with(
            'success',
            "{$formatted} guardado na meta \"{$updated->name}\"!{$doneNotice}"
        );
    }

    public function withdraw(
        Request $request,
        SavingsGoal $goal,
        WithdrawSavingsGoalAction $withdrawAction,
    ): RedirectResponse {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1.00', 'max:50000.00'],
        ]);

        try {
            $updated = $withdrawAction->execute($request->user(), $goal, (float) $validated['amount']);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $formatted = 'R$ '.number_format((float) $validated['amount'], 2, ',', '.');

        return redirect()->route('card.show')->with(
            'success',
            "{$formatted} resgatado da meta \"{$updated->name}\" direto para o saldo do seu cartão!"
        );
    }

    public function destroy(
        Request $request,
        SavingsGoal $goal,
        WithdrawSavingsGoalAction $withdrawAction,
    ): RedirectResponse {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 403);

        $remaining = (float) $goal->current_amount;
        $goalName = $goal->name;

        try {
            if ($remaining > 0) {
                $withdrawAction->execute($request->user(), $goal, $remaining);
            }
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $goal->delete();

        $refundMsg = $remaining > 0
            ? ' R$ '.number_format($remaining, 2, ',', '.').' devolvidos para sua carteira.'
            : '';

        return redirect()->route('card.show')->with(
            'success',
            "Meta \"{$goalName}\" removida.{$refundMsg}"
        );
    }
}
