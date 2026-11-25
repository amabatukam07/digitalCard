<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tradeName = fake()->company();

        return [
            'cnpj' => sprintf(
                '%02d.%03d.%03d/0001-%02d',
                fake()->numberBetween(10, 99),
                fake()->numberBetween(100, 999),
                fake()->numberBetween(100, 999),
                fake()->numberBetween(10, 99)
            ),
            'legal_name' => $tradeName.' Ltda',
            'trade_name' => $tradeName,
            'state_registration' => 'ISENTO',
            'email' => fake()->unique()->companyEmail(),
            'phone' => '(11) 9'.fake()->numerify('####-####'),
            'billing_address' => fake()->streetAddress().' — São Paulo/SP',
            'billing_cep' => '01310-200',
            'status' => Company::STATUS_ACTIVE,
            'require_order_approval' => false,
            'low_balance_threshold' => 200.00,
        ];
    }
}
