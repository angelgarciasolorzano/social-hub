<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $testResponse = $this->get(route('login'));

        $testResponse
            ->assertOk()
            ->assertSee('resources/js/modules/auth/login/Login.tsx', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $testResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $testResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $testResponse = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $testResponse->assertRedirect(route('two-factor.login'));
        $testResponse->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $testResponse = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $testResponse->assertRedirect(route('home'));
    }

    public function test_users_are_rate_limited(): void
    {
        $user = User::factory()->create();

        RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);

        $testResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $testResponse->assertSessionHasErrors('email');

        $errors = session('errors');

        $this->assertStringContainsString('Too many login attempts', (string) $errors->first('email'));
    }
}
