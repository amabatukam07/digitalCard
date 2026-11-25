<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('NexoSupri');
    }

    public function test_new_users_can_register_and_provision_company_and_wallet(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mariana Empresária',
            'email' => 'mariana@novaempresa.com.br',
            'company_trade_name' => 'Nova Empresa B2B',
            'company_legal_name' => 'Nova Empresa B2B Ltda',
            'cnpj' => '11.222.333/0001-81',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('company.dashboard', absolute: false));

        $user = User::where('email', 'mariana@novaempresa.com.br')->firstOrFail();
        $company = Company::where('cnpj', '11.222.333/0001-81')->firstOrFail();

        $this->assertTrue($user->isCompanyManager($company));
        $this->assertNotNull($company->wallet);
        $this->assertEquals(0.00, (float) $company->wallet->balance);
    }
}
