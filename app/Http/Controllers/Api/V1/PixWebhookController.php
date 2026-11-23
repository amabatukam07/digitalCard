<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');
        $signature = (string) $request->header('X-Webhook-Signature', '');
        $tolerance = (int) config('digitalcard.pix.webhook_tolerance_seconds', 300);
        $secret = (string) config('digitalcard.pix.webhook_secret', '');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return response()->json(['message' => 'Timestamp do webhook inválido ou expirado.'], 401);
        }

        $rawBody = (string) $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        $normalizedSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        if ($normalizedSignature === '' || ! hash_equals($expectedSignature, $normalizedSignature)) {
            return response()->json(['message' => 'Assinatura HMAC do webhook inválida.'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:120'],
            'txid' => ['required', 'string', 'max:64', 'exists:recharges,txid'],
        ]);

        $existingEvent = WebhookEvent::where('provider', 'pix')
            ->where('event_id', $validated['event_id'])
            ->first();

        if ($existingEvent && $existingEvent->status === 'processed') {
            return response()->json([
                'status' => 'duplicate_ignored',
                'event_id' => $validated['event_id'],
            ], 200);
        }

        try {
            $event = WebhookEvent::create([
                'provider' => 'pix',
                'event_id' => $validated['event_id'],
                'payload_hash' => hash('sha256', $rawBody),
                'status' => 'processing',
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'status' => 'duplicate_ignored',
                'event_id' => $validated['event_id'],
            ], 200);
        }

        try {
            $recharge = $gateway->confirmPixWebhook($validated['txid']);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return response()->json([
                'status' => 'processed',
                'event_id' => $validated['event_id'],
                'txid' => $recharge->txid,
                'recharge_status' => $recharge->status,
            ], 200);
        } catch (\Throwable $e) {
            $event->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }
}
