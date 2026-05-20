<?php

namespace Tests\Feature\Auth;

use App\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/confirm-password')
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_confirm_password_screen_redirects_to_the_last_intended_page_when_password_is_still_confirmed()
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                'auth.password_confirmation_redirect' => route('two-factor.show', absolute: false),
            ])
            ->get(route('password.confirm'));

        $response->assertRedirect(route('two-factor.show', absolute: false));
    }
}
