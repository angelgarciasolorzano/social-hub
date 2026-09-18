<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $testResponse = $this->actingAs($user)->get(route('verification.notice'));

        $testResponse->assertOk();
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        $testResponse = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())
            ->toBeTrue();
        $testResponse->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        expect($user->fresh()->hasVerifiedEmail())
            ->toBeFalse();
    }

    public function test_email_is_not_verified_with_invalid_user_id(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 123, 'hash' => sha1((string) $user->email)]
        );

        $this->actingAs($user)->get($verificationUrl);

        expect($user->fresh()->hasVerifiedEmail())
            ->toBeFalse();
    }

    public function test_verified_user_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $testResponse = $this->actingAs($user)->get(route('verification.notice'));

        $testResponse->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        expect($user->fresh()->hasVerifiedEmail())
            ->toBeTrue();
        Event::assertNotDispatched(Verified::class);
    }
}
