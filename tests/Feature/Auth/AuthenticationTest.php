<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'user_type' => 'cliente',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('company.dashboard', absolute: false));
    }

    public function test_admin_user_can_authenticate_via_admin_login_and_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_regular_user_cannot_authenticate_as_administrator(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $regularUser->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_admin_login_ignores_stale_client_intended_url_and_preserves_admin_intended_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withSession(['url.intended' => route('company.dashboard', absolute: false)])
            ->post('/login', [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->post('/logout');

        $this->withSession(['url.intended' => route('wallet.show', absolute: false)])
            ->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->post('/logout');

        $this->withSession(['url.intended' => route('admin.orders.index', absolute: false)])
            ->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.orders.index', absolute: false));
    }

    public function test_authenticated_admin_is_redirected_to_admin_dashboard_from_client_and_guest_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($admin)->get(route('admin.login'))->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($admin)->get(route('company.dashboard'))->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($admin)->get(route('wallet.show'))->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_regular_user_login_ignores_stale_admin_intended_url_and_unlinked_user_gets_403(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $this->withSession(['url.intended' => route('admin.dashboard', absolute: false)])
            ->post('/login', [
                'email' => $regularUser->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('company.dashboard', absolute: false));

        $this->actingAs($regularUser)
            ->get(route('company.dashboard'))
            ->assertForbidden();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
