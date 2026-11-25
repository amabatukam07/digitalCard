<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account_when_balance_is_zero_and_no_open_orders(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
    }

    public function test_s7_user_cannot_delete_sole_manager_with_positive_company_balance_or_open_order(): void
    {
        $company = Company::factory()->create();
        $company->wallet()->update(['balance' => 150.00]);
        $user = User::factory()->forCompany($company)->create();

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'balance')
            ->assertRedirect('/profile');

        $this->assertNotNull(User::find($user->id));

        // Zera o saldo, mas cria pedido em aberto
        $company->wallet()->update(['balance' => 0.00]);
        Order::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subtotal_amount' => 100.00,
            'discount_amount' => 0.00,
            'shipping_cost' => 0.00,
            'total_amount' => 100.00,
            'credits_debited' => 100.00,
            'total_cost_snapshot' => 60.00,
            'estimated_das_amount' => 4.00,
            'estimated_margin_amount' => 36.00,
            'status' => Order::STATUS_REQUESTED,
            'shipping_address' => 'Rua Aberta, 10',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'orders')
            ->assertRedirect('/profile');

        $this->assertNotNull(User::find($user->id));
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull(User::find($user->id));
    }
}
