<?php

namespace Tests\Unit;

use App\Services\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_regional_rates_and_free_shipping_threshold(): void
    {
        $calculator = app(ShippingCalculator::class);

        $spUnder = $calculator->calculate('01001-000', 49999);
        $this->assertFalse($spUnder['is_free']);
        $this->assertSame('SP', $spUnder['uf']);
        $this->assertSame(1990, $spUnder['cost_cents']);

        $spExactFree = $calculator->calculate('01001-000', 50000);
        $this->assertTrue($spExactFree['is_free']);
        $this->assertSame(0, $spExactFree['cost_cents']);
    }

    public function test_viacep_failure_is_not_cached_while_valid_response_is_cached(): void
    {
        $calculator = app(ShippingCalculator::class);
        $originalEnv = app()->environment();
        Cache::forget('viacep:01001000');

        Http::fake([
            'viacep.com.br/ws/01001000/json/' => Http::sequence()
                ->push(['erro' => true], 200)
                ->push([
                    'cep' => '01001-000',
                    'logradouro' => 'Praça da Sé',
                    'bairro' => 'Sé',
                    'localidade' => 'São Paulo',
                    'uf' => 'SP',
                ], 200),
        ]);

        app()->detectEnvironment(fn () => 'local');
        try {
            $fallback = $calculator->lookupCep('01001-000');
            $this->assertSame('Avenida Empresarial (Consulta Simulada)', $fallback['street']);
            $this->assertFalse(Cache::has('viacep:01001000'));

            $resolved = $calculator->lookupCep('01001-000');
            $this->assertSame('Praça da Sé', $resolved['street']);
            $this->assertTrue(Cache::has('viacep:01001000'));
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }
}
