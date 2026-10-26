<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Livewire\AcceptAdminInvitation;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('shows the fixed email and does not ask an existing account for credentials', function (): void {
    $targetUser = createUser();
    $invitationData = createAdminInvitationWithToken($targetUser);

    Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
        ->assertSet('email', $targetUser->email)
        ->assertSet('requiresRegistration', false)
        ->assertSee($targetUser->email)
        ->assertDontSee('wire:model="email"')
        ->assertDontSee('wire:model="name"')
        ->assertDontSee('wire:model="password"');
});

it('does not allow the invitation token or email to be changed by the client', function (): void {
    $targetUser = createUser();
    $invitationData = createAdminInvitationWithToken($targetUser);

    expect(fn () => Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
        ->set('email', 'attacker@example.com'))
        ->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
            ->set('token', bin2hex(random_bytes(32))))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('accepts an existing account invitation without changing its credentials', function (): void {
    $targetUser = createUser();
    $originalName = $targetUser->name;
    $originalPassword = $targetUser->password;
    $targetUser->forceFill(['email_verified_at' => null])->save();
    $invitationData = createAdminInvitationWithToken($targetUser);

    Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
        ->call('accept')
        ->assertRedirect(route('filament.admin.auth.login'));

    $targetUser->refresh();

    expect($targetUser->name)
        ->toBe($originalName)
        ->and($targetUser->password)
        ->toBe($originalPassword)
        ->and($targetUser->hasVerifiedEmail())
        ->toBeTrue()
        ->and($targetUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeTrue()
        ->and($invitationData['invitation']->refresh()->accepted_at)
        ->not->toBeNull();
});

it('creates and verifies a new account from the invited email', function (): void {
    $invitedEmail = 'new-admin@example.com';
    $invitationData = createAdminInvitationWithToken(email: $invitedEmail);

    $testable = Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']]);
    $testable->assertSet('requiresRegistration', true);
    $testable->assertSee($invitedEmail);
    $testable->set('name', 'New Administrator');
    $testable->set('password', 'invite-password-9');
    $testable->set('password_confirmation', 'invite-password-9');
    $testable->call('accept');
    $testable->assertHasNoErrors();
    $testable->assertRedirect(route('filament.admin.auth.login'));

    $newUser = User::query()->where('email', $invitedEmail)->firstOrFail();

    expect($newUser->name)
        ->toBe('New Administrator')
        ->and(Hash::check('invite-password-9', $newUser->password))
        ->toBeTrue()
        ->and($newUser->hasVerifiedEmail())
        ->toBeTrue()
        ->and($newUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeTrue()
        ->and($invitationData['invitation']->refresh()->accepted_at)
        ->not->toBeNull();
});

it('rejects a new account password that does not match its confirmation', function (): void {
    $invitedEmail = 'new-admin@example.com';
    $invitationData = createAdminInvitationWithToken(email: $invitedEmail);

    Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
        ->set('name', 'New Administrator')
        ->set('password', 'invite-password-9')
        ->set('password_confirmation', 'different-password-9')
        ->call('accept')
        ->assertHasErrors(['password' => 'confirmed']);

    expect(User::query()->where('email', $invitedEmail)->exists())
        ->toBeFalse()
        ->and($invitationData['invitation']->refresh()->accepted_at)
        ->toBeNull();
});

it('does not accept an expired invitation or create an account', function (): void {
    $invitedEmail = 'new-admin@example.com';
    $invitationData = createAdminInvitationWithToken(email: $invitedEmail);
    $invitationData['invitation']->forceFill([
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ])->save();

    Livewire::test(AcceptAdminInvitation::class, ['token' => $invitationData['token']])
        ->assertSet('isInvitationValid', false)
        ->assertDontSee('wire:model="name"')
        ->call('accept');

    expect(User::query()->where('email', $invitedEmail)->exists())
        ->toBeFalse()
        ->and($invitationData['invitation']->refresh()->accepted_at)
        ->toBeNull();
});
