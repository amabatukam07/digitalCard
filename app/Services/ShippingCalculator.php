<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShippingCalculator
{
    /**
     * Calcula o frete corporativo com base no CEP e subtotal líquido em centavos.
     *
     * @return array{cost_cents: int, cost: float, is_free: bool, estimated_days: int, region: string, uf: string, free_shipping_min: float}
     */
    public function calculate(string $cep, int $merchandiseTotalCents, ?int $freeShippingMinCents = null): array
    {
        $cleanCep = preg_replace('/\D/', '', $cep) ?: '01310200';
        $firstDigit = (int) substr(str_pad($cleanCep, 8, '0'), 0, 1);

        $defaultMinCents = CartPricing::toCents((float) config('nexosupri.free_shipping_min_total', 500.00));
        $freeShippingMinCents = $freeShippingMinCents !== null ? max(0, $freeShippingMinCents) : $defaultMinCents;

        [$region, $uf, $baseCostCents, $days] = match ($firstDigit) {
            0, 1 => ['Sudeste (SP)', 'SP', 1990, 2],
            2 => ['Sudeste (RJ/ES)', 'RJ', 2290, 3],
            3 => ['Sudeste (MG)', 'MG', 2290, 3],
            4 => ['Nordeste (BA/SE)', 'BA', 2490, 4],
            5 => ['Nordeste (PE/AL/PB/RN)', 'AL', 2490, 4],
            6 => ['Norte/Nordeste (CE/PI/MA/PA/AM)', 'CE', 2690, 5],
            7 => ['Centro-Oeste/Norte (DF/GO/MT/MS/TO/RO/AC)', 'DF', 2490, 4],
            8, 9 => ['Sul (PR/SC/RS)', 'PR', 2590, 4],
            default => ['Nacional', 'BR', 2490, 4],
        };

        $isFree = $merchandiseTotalCents >= $freeShippingMinCents;
        $finalCostCents = $isFree ? 0 : $baseCostCents;

        return [
            'cost_cents' => $finalCostCents,
            'cost' => CartPricing::toDecimal($finalCostCents),
            'is_free' => $isFree,
            'estimated_days' => $days,
            'region' => $region,
            'uf' => $uf,
            'free_shipping_min' => CartPricing::toDecimal($freeShippingMinCents),
        ];
    }

    /**
     * Consulta endereço no ViaCEP com cache de 24h e fallback offline determinístico.
     *
     * @return array{cep: string, street: string, neighborhood: string, city: string, state: string}
     */
    public function lookupCep(string $cep): array
    {
        $clean = substr(preg_replace('/\D/', '', $cep) ?: '', 0, 8);
        $formatted = strlen($clean) === 8
            ? substr($clean, 0, 5).'-'.substr($clean, 5, 3)
            : $cep;

        if (strlen($clean) !== 8) {
            return [
                'cep' => $formatted,
                'street' => 'Endereço informado manualmente',
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'state' => 'SP',
            ];
        }

        $cacheKey = "viacep:{$clean}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['cep'], $cached['city'], $cached['state'])) {
            return $cached;
        }

        if (! app()->environment('testing')) {
            try {
                $response = Http::timeout(2)->acceptJson()->get("https://viacep.com.br/ws/{$clean}/json/");
                if ($response->successful() && ! $response->json('erro')) {
                    $data = $response->json();

                    $resolved = [
                        'cep' => (string) ($data['cep'] ?? $formatted),
                        'street' => (string) ($data['logradouro'] ?: 'Logradouro geral do CEP'),
                        'neighborhood' => (string) ($data['bairro'] ?: 'Centro'),
                        'city' => (string) ($data['localidade'] ?: 'São Paulo'),
                        'state' => (string) ($data['uf'] ?: 'SP'),
                    ];

                    Cache::put($cacheKey, $resolved, 86400);

                    return $resolved;
                }
            } catch (\Throwable) {
                // Fallback offline sem poluir o cache de 24h
            }
        }

        $quote = $this->calculate($clean, 0);

        return [
            'cep' => $formatted,
            'street' => 'Avenida Empresarial (Consulta Simulada)',
            'neighborhood' => 'Centro',
            'city' => $quote['uf'] === 'SP' ? 'São Paulo' : 'Capital ('.$quote['uf'].')',
            'state' => $quote['uf'],
        ];
    }
}
