<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '(11) 9'.fake()->numerify('####-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_admin' => false,
            'role' => UserRole::CompanyManager->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'role' => UserRole::Admin->value,
        ]);
    }

    public function finance(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => UserRole::Finance->value,
        ]);
    }

    public function purchasing(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => UserRole::Purchasing->value,
        ]);
    }

    public function logistics(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => UserRole::Logistics->value,
        ]);
    }

    public function commercial(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => UserRole::Commercial->value,
        ]);
    }

    public function auditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => UserRole::Auditor->value,
        ]);
    }

    public function forCompany(
        Company $company,
        string $companyRole = CompanyUser::ROLE_MANAGER,
        ?float $spendingLimit = null,
        string $department = 'Compras',
    ): static {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'role' => $companyRole === CompanyUser::ROLE_MANAGER
                ? UserRole::CompanyManager->value
                : UserRole::CompanyRequester->value,
        ])->afterCreating(function (User $user) use ($company, $companyRole, $spendingLimit, $department): void {
            CompanyUser::updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                [
                    'company_role' => $companyRole,
                    'spending_limit' => $spendingLimit,
                    'department' => $department,
                    'is_active' => true,
                ]
            );
        });
    }
}
