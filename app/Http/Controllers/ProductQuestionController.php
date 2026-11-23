<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Notifications\ProductQuestionAnsweredNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductQuestionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'question' => trim((string) $validated['question']),
            'is_public' => true,
        ]);

        return back()->with('success', 'Pergunta enviada! Você será notificado assim que nossa equipe responder.');
    }

    public function answer(Request $request, ProductQuestion $question): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $question->loadMissing(['product', 'user']);

        $question->update([
            'answer' => trim((string) $validated['answer']),
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        AuditLog::record(
            'product_question.answered',
            $question,
            null,
            [
                'product_id' => $question->product_id,
                'question_id' => $question->id,
            ],
            $request->user()->id
        );

        if ($question->user && $question->product) {
            $question->user->notify(new ProductQuestionAnsweredNotification($question->product, $question->fresh()));
        }

        return back()->with('success', 'Resposta publicada e cliente notificado com sucesso.');
    }
}
