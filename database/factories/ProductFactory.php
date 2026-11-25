<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $cost = fake()->randomFloat(2, 5, 40);
        $freight = round($cost * 0.05, 2);
        $totalCost = $cost + $freight;
        $price = ceil(round(($totalCost * 1.35 / 0.96) * 10, 6)) / 10;

        return [
            'category_id' => Category::factory(),
            'sku' => 'NX-'.strtoupper(fake()->unique()->bothify('???###')),
            'barcode' => fake()->ean13(),
            'brand' => 'NexoSupri',
            'unit' => 'pacote',
            'presentation' => 'Pacote padrão',
            'name' => ucfirst($name),
            'slug' => str($name)->slug()->value(),
            'description' => fake()->paragraph(),
            'cost_price' => $cost,
            'inbound_freight' => $freight,
            'other_costs' => 0.00,
            'markup_percent' => 35.00,
            'das_rate' => 4.00,
            'price' => $price,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(15, 80),
            'min_stock' => 10,
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
