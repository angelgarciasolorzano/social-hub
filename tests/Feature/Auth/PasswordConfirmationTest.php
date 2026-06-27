<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $testResponse = $this->actingAs($user)->get(route('password.confirm'));

        $testResponse->assertOk();

        $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/confirm-password')
        );
    }

    public function test_password_confirmation_requires_authentication(): void
    {
        $testResponse = $this->get(route('password.confirm'));

        $testResponse->assertRedirect(route('login'));
    }

    public function test_confirm_password_screen_redirects_to_the_last_intended_page_when_password_is_still_confirmed(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $testResponse = $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => Date::now()
                    ->getTimestamp(),
                'auth.password_confirmation_redirect' => route('two-factor.show', absolute: false),
            ])
            ->get(route('password.confirm'));

        $testResponse->assertRedirect(route('two-factor.show', absolute: false));
    }
}
