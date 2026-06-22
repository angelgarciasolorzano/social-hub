<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_page_can_be_rendered(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()
                ->getTimestamp()])
            ->get(route('two-factor.show'))
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('settings/two-factor')
                ->where('twoFactorEnabled', false)
            );
    }

    public function test_two_factor_settings_page_requires_password_confirmation_when_enabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        /** @var User $user */
        $user = User::factory()->createOne();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $testResponse = $this->actingAs($user)
            ->get(route('two-factor.show'));

        $testResponse->assertRedirect(route('password.confirm'));
    }

    public function test_two_factor_settings_page_does_not_requires_password_confirmation_when_disabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        /** @var User $user */
        $user = User::factory()->createOne();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]);

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('settings/two-factor')
            );
    }

    public function test_two_factor_settings_page_returns_forbidden_response_when_two_factor_is_disabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        config(['fortify.features' => []]);

        /** @var User $user */
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()
                ->getTimestamp()])
            ->get(route('two-factor.show'))
            ->assertForbidden();
    }
}
