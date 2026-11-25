<?php

namespace Tests\Unit;

use App\Services\PricingCalculator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_unit_cost_suggested_price_and_das_margin_analysis(): void
    {
        $calculator = app(PricingCalculator::class);

        $totalCost = $calculator->totalUnitCost(24.00, 1.00, 0.00);
        $this->assertSame(25.00, $totalCost);

        // Preço = 25 * 1.35 / (1 - 0.04) = 33.75 / 0.96 = 35.15625 -> arredonda p/ cima 0,10 = 35.20
        $roundedPrice = $calculator->suggestedPrice($totalCost, 35.0, 4.0, 0.0, true);
        $this->assertSame(35.20, $roundedPrice);

        $exactPrice = $calculator->suggestedPrice($totalCost, 35.0, 4.0, 0.0, false);
        $this->assertSame(35.16, $exactPrice);

        $analysis = $calculator->analyze(35.20, 25.00, 4.0);
        $this->assertSame(1.41, $analysis['das_amount']);
        $this->assertSame(8.79, $analysis['result_after_das']);
        $this->assertGreaterThan(24.0, $analysis['margin_percent']);
        $this->assertSame(40.80, $analysis['effective_markup_percent']);
    }

    public function test_simulates_portfolio_with_seeded_catalog_and_thousand_credits(): void
    {
        $this->seed(CatalogSeeder::class);

        $calculator = app(PricingCalculator::class);
        $simulation = $calculator->simulatePortfolio(1000.0, 35.0, 4.0);

        $this->assertSame(1000.00, $simulation['credits_available']);
        $this->assertGreaterThan(500.00, $simulation['simulated_sales']);
        $this->assertLessThanOrEqual(1000.00, $simulation['simulated_sales']);
        $this->assertGreaterThan(0.0, $simulation['merchandise_and_freight_cost']);
        $this->assertGreaterThan(0.0, $simulation['estimated_das']);
        $this->assertGreaterThan(0.0, $simulation['result_after_cost_and_das']);
        $this->assertNotEmpty($simulation['items']);

        $smallSimulation = $calculator->simulatePortfolio(500.0, 35.0, 4.0);
        $this->assertSame(500.00, $smallSimulation['credits_available']);
        $this->assertLessThanOrEqual(500.00, $smallSimulation['simulated_sales']);
        $this->assertGreaterThanOrEqual(8, count($smallSimulation['items']));
    }
}
