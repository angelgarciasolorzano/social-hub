<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_update_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->get(route('password.edit'));

        $testResponse->assertOk();
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('password.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $testResponse
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('password.edit'));
    }
}
