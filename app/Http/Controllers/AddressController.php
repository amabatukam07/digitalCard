<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\ShippingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function store(Request $request, ShippingCalculator $shippingCalculator): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'cep' => ['required', 'string', 'max:15'],
            'street' => ['nullable', 'string', 'max:180'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $viaCep = $shippingCalculator->lookupCep($validated['cep']);
        $user = $request->user();
        $hasExisting = $user->addresses()->exists();
        $makeDefault = $request->boolean('is_default') || ! $hasExisting;

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'label' => ($validated['label'] ?? null) ?: 'Principal',
            'recipient_name' => ($validated['recipient_name'] ?? null) ?: $user->name,
            'cep' => substr($viaCep['cep'], 0, 9),
            'street' => ($validated['street'] ?? null) ?: $viaCep['street'],
            'number' => ($validated['number'] ?? null) ?: 'S/N',
            'complement' => $validated['complement'] ?? null,
            'neighborhood' => ($validated['neighborhood'] ?? null) ?: $viaCep['neighborhood'],
            'city' => ($validated['city'] ?? null) ?: $viaCep['city'],
            'state' => strtoupper(($validated['state'] ?? null) ?: substr($viaCep['state'], 0, 2)),
            'is_default' => $makeDefault,
        ]);

        if ($makeDefault) {
            $request->session()->put('shipping_cep', $address->cep);
            $request->session()->put('shipping_address', $address->formattedLine());
        }

        return back()->with('success', 'Endereço salvo no seu livro de endereços.');
    }

    public function setDefault(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('manage', $address);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        $request->session()->put('shipping_cep', $address->cep);
        $request->session()->put('shipping_address', $address->formattedLine());

        return back()->with('success', 'Endereço padrão atualizado.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('manage', $address);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $fallback = $request->user()->addresses()->latest()->first();
            if ($fallback) {
                $fallback->update(['is_default' => true]);
                $request->session()->put('shipping_cep', $fallback->cep);
                $request->session()->put('shipping_address', $fallback->formattedLine());
            } else {
                $request->session()->forget(['shipping_cep', 'shipping_address']);
            }
        }

        return back()->with('success', 'Endereço removido com sucesso.');
    }
}
