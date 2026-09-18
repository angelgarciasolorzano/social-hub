<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $testResponse->assertOk()
            ->assertSeeHtml('resources/js/modules/profile/Profile.tsx');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        expect($user->name)
            ->toBe('Test User')
            ->and($user->email)
            ->toBe('test@example.com')
            ->and($user->email_verified_at)
            ->toBeNull();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->email_verified_at)->not->toBeNull();
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        expect($user->fresh())
            ->toBeNull();
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $testResponse
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        expect($user->fresh())->not->toBeNull();
    }
}
