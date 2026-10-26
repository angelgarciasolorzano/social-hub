<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Mail\AdminInvitationMail;
use App\Modules\Admin\Models\AdminInvitation;
use App\Modules\Admin\Services\AdminInvitationService;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

const ADMIN_INVITATION_ACCEPT_ROUTE = 'admin.invitations.accept';

/**
 * Get the bearer token from the queued invitation email.
 */
function adminInvitationTokenFromMail(string $recipientEmail, string $expectedTokenHash): string
{
    $plainToken = null;

    Mail::assertQueued(
        AdminInvitationMail::class,
        function (AdminInvitationMail $adminInvitationMail) use ($expectedTokenHash, $recipientEmail, &$plainToken): bool {
            if (! $adminInvitationMail->hasTo($recipientEmail)) {
                return false;
            }

            $matchedLinkCount = preg_match_all('/href="([^"]+)"/', $adminInvitationMail->render(), $matches);

            if ($matchedLinkCount === false || $matchedLinkCount === 0) {
                return false;
            }

            foreach ($matches[1] as $actionUrl) {
                $decodedUrl = html_entity_decode($actionUrl, ENT_QUOTES | ENT_HTML5);
                $path = parse_url($decodedUrl, PHP_URL_PATH);

                if (! is_string($path)) {
                    continue;
                }

                $tokenFromUrl = basename($path);

                if (hash('sha256', $tokenFromUrl) !== $expectedTokenHash) {
                    continue;
                }

                $plainToken = $tokenFromUrl;

                return true;
            }

            return false;
        },
    );

    return $plainToken ?? throw new LogicException('The invitation token was not present in the email action URL.');
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Route::name(ADMIN_INVITATION_ACCEPT_ROUTE)->group(function (): void {
        Route::get(
            '/testing/admin-invitations/{token}',
            static fn (string $token): string => $token,
        );
    });
});

it('sends a one-time token to the selected user without granting admin before acceptance', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue($invitedByUser, $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);

    expect($adminInvitation->target_user_id)
        ->toBe($targetUser->getKey())
        ->and($adminInvitation->token_hash)
        ->toBe(hash('sha256', $plainToken))
        ->not->toBe($plainToken)
        ->and($targetUser->refresh()->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeFalse();
});

it('matches a registered email to its existing account after normalization', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue(
        $invitedByUser,
        '  '.mb_strtoupper($targetUser->email).'  ',
    );

    expect($adminInvitation->email)
        ->toBe($targetUser->email)
        ->and($adminInvitation->target_user_id)
        ->toBe($targetUser->getKey());
});

it('issues an invitation to an unregistered email without creating an account', function (): void {
    $invitedByUser = createUser();
    $newEmail = 'new-admin@example.com';
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue($invitedByUser, $newEmail);

    expect($adminInvitation->target_user_id)
        ->toBeNull()
        ->and(User::query()->where('email', $newEmail)->exists())
        ->toBeFalse();
});

it('invalidates the previous token when a new invitation is issued', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $targetUser->email, $targetUser);
    $previousToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);
    $currentInvitation = $adminInvitationService->issue($invitedByUser, $targetUser->email, $targetUser);
    $currentToken = adminInvitationTokenFromMail($targetUser->email, $currentInvitation->token_hash);

    expect($adminInvitation->refresh()->expires_at->isPast())
        ->toBeTrue()
        ->and($adminInvitationService->findValidInvitation($previousToken))
        ->toBeNull()
        ->and($adminInvitationService->findValidInvitation($currentToken)?->getKey())
        ->toBe($currentInvitation->getKey());
});

it('keeps an invitation pending when queue dispatch fails', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    $mock = Mockery::mock(PendingMail::class);
    $mock->shouldReceive('send')
        ->once()
        ->with(Mockery::type(AdminInvitationMail::class))
        ->andThrow(new RuntimeException('Mail service unavailable.'));
    Mail::shouldReceive('to')
        ->once()
        ->with($targetUser->email)
        ->andReturn($mock);

    expect(fn (): AdminInvitation => resolve(AdminInvitationService::class)->issue(
        $invitedByUser,
        $targetUser->email,
        $targetUser,
    ))->toThrow(RuntimeException::class, 'Mail service unavailable.');

    $adminInvitation = AdminInvitation::query()->where('email', $targetUser->email)->firstOrFail();

    expect($adminInvitation->accepted_at)
        ->toBeNull()
        ->and($targetUser->refresh()->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeFalse();
});

it('accepts an invitation for an existing account without changing its credentials', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    $originalPasswordHash = $targetUser->password;
    $originalName = $targetUser->name;
    $targetUser->forceFill(['email_verified_at' => null])->save();
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue($invitedByUser, $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);
    $acceptedUser = resolve(AdminInvitationService::class)->accept($plainToken, 'Ignored name', 'ignored password');

    expect($acceptedUser->getKey())
        ->toBe($targetUser->getKey())
        ->and($acceptedUser->name)
        ->toBe($originalName)
        ->and($acceptedUser->password)
        ->toBe($originalPasswordHash)
        ->and($acceptedUser->hasVerifiedEmail())
        ->toBeTrue()
        ->and($acceptedUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeTrue()
        ->and($adminInvitation->refresh()->accepted_at)
        ->not->toBeNull();
});

it('creates and verifies a new account from the invited email and allowed registration data', function (): void {
    $invitedByUser = createUser();
    $newEmail = 'new-admin@example.com';
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue($invitedByUser, $newEmail);
    $plainToken = adminInvitationTokenFromMail($newEmail, $adminInvitation->token_hash);
    $newUser = resolve(AdminInvitationService::class)->accept($plainToken, 'New Administrator', 'strong-password-value');

    expect($newUser->name)
        ->toBe('New Administrator')
        ->and($newUser->email)
        ->toBe($newEmail)
        ->and(Hash::check('strong-password-value', $newUser->password))
        ->toBeTrue()
        ->and($newUser->hasVerifiedEmail())
        ->toBeTrue()
        ->and($newUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeTrue()
        ->and(User::query()->where('email', $newEmail)->count('*'))
        ->toBe(1);
});

it('requires registration fields before accepting a new user invitation', function (): void {
    $invitedByUser = createUser();
    $newEmail = 'new-admin@example.com';
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $newEmail);
    $plainToken = adminInvitationTokenFromMail($newEmail, $adminInvitation->token_hash);

    expect(fn (): User => $adminInvitationService->accept($plainToken, null, 'strong-password-value'))
        ->toThrow(ValidationException::class)
        ->and(User::query()->where('email', $newEmail)
            ->exists())
        ->toBeFalse()
        ->and($adminInvitation->refresh()->accepted_at)
        ->toBeNull();
});

it('rejects an altered invitation token without creating a user or assigning a role', function (): void {
    $invitedByUser = createUser();
    $newEmail = 'new-admin@example.com';
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $newEmail);
    $plainToken = adminInvitationTokenFromMail($newEmail, $adminInvitation->token_hash);

    expect(fn (): User => $adminInvitationService->accept('altered-'.$plainToken, 'New Administrator', 'strong-password-value'))
        ->toThrow(ValidationException::class)
        ->and(User::query()->where('email', $newEmail)
            ->exists())
        ->toBeFalse()
        ->and($adminInvitation->refresh()->accepted_at)
        ->toBeNull();
});

it('rejects an expired invitation without creating a user', function (): void {
    $invitedByUser = createUser();
    $newEmail = 'new-admin@example.com';
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $newEmail);
    $plainToken = adminInvitationTokenFromMail($newEmail, $adminInvitation->token_hash);
    $adminInvitation->forceFill(['expires_at' => CarbonImmutable::now()->subMinute()])->save();

    expect(fn (): User => $adminInvitationService->accept($plainToken, 'New Administrator', 'strong-password-value'))
        ->toThrow(ValidationException::class)
        ->and($adminInvitationService->findValidInvitation($plainToken))
        ->toBeNull()
        ->and(User::query()->where('email', $newEmail)
            ->exists())
        ->toBeFalse()
        ->and($adminInvitation->refresh()->accepted_at)
        ->toBeNull();
});

it('rejects a previously accepted invitation token', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);
    $adminInvitationService->accept($plainToken);

    expect(fn (): User => $adminInvitationService->accept($plainToken))
        ->toThrow(ValidationException::class)
        ->and($targetUser->refresh()->getRoleNames()->count())
        ->toBe(1)
        ->and($targetUser->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeTrue()
        ->and($adminInvitation->refresh()->accepted_at)->not->toBeNull();
});

it('rejects an invitation if the existing user email no longer matches', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();
    $adminInvitationService = resolve(AdminInvitationService::class);

    $adminInvitation = $adminInvitationService->issue($invitedByUser, $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);
    $targetUser->forceFill(['email' => 'changed-email@example.com'])->save();

    expect(fn (): User => $adminInvitationService->accept($plainToken))
        ->toThrow(ValidationException::class)
        ->and($targetUser->refresh()
            ->hasRole(AdminRole::Admin->value, 'admin'))
        ->toBeFalse()
        ->and($adminInvitation->refresh()->accepted_at)
        ->toBeNull();
});

it('records who issued the invitation and for which account without the email or token', function (): void {
    $invitedByUser = createUser();
    $targetUser = createUser();
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue($invitedByUser, $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);

    $activity = Activity::query()->where('event', 'admin.invitation_issued')->sole();
    $storedActivity = $activity->withoutRelations()->toJson();

    expect($activity)
        ->log_name->toBe('admin')
        ->subject_type->toBe('admin_invitation')
        ->subject_id->toBe($adminInvitation->id)
        ->causer_id->toBe($invitedByUser->id)
        ->and($activity->properties?->get('target_user_id'))->toBe($targetUser->id)
        ->and($storedActivity)->not->toContain($targetUser->email)
        ->not->toContain($plainToken)
        ->not->toContain($adminInvitation->token_hash);
});

it('records an invitation issued to an unregistered email without the email', function (): void {
    Mail::fake();

    resolve(AdminInvitationService::class)->issue(createUser(), 'new-admin@example.com');

    $activity = Activity::query()->where('event', 'admin.invitation_issued')->sole();

    expect($activity->properties?->get('target_user_id'))->toBeNull()
        ->and($activity->withoutRelations()->toJson())->not->toContain('new-admin@example.com');
});

it('records an accepted invitation that created a new account without the password', function (): void {
    $newEmail = 'new-admin@example.com';
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue(createUser(), $newEmail);
    $plainToken = adminInvitationTokenFromMail($newEmail, $adminInvitation->token_hash);
    $newUser = resolve(AdminInvitationService::class)->accept($plainToken, 'New Administrator', 'strong-password-value');

    $activity = Activity::query()->where('event', 'admin.invitation_accepted')->sole();

    expect($activity)
        ->subject_id->toBe($adminInvitation->id)
        ->causer_id->toBe($newUser->id)
        ->and($activity->properties?->only(['account_created', 'roles_assigned'])->all())
        ->toEqual(['account_created' => true, 'roles_assigned' => [AdminRole::Admin->value]])
        ->and($activity->withoutRelations()->toJson())->not->toContain('strong-password-value')
        ->not->toContain($plainToken);
});

it('records an accepted invitation for an existing administrator without assigned roles', function (): void {
    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value);
    Mail::fake();

    $adminInvitation = resolve(AdminInvitationService::class)->issue(createUser(), $targetUser->email, $targetUser);
    $plainToken = adminInvitationTokenFromMail($targetUser->email, $adminInvitation->token_hash);
    resolve(AdminInvitationService::class)->accept($plainToken);

    expect(Activity::query()->where('event', 'admin.invitation_accepted')->sole()->properties?->only(['account_created', 'roles_assigned'])->all())
        ->toEqual(['account_created' => false, 'roles_assigned' => []]);
});

it('does not record the acceptance of an altered token', function (): void {
    $targetUser = createUser();
    Mail::fake();

    resolve(AdminInvitationService::class)->issue(createUser(), $targetUser->email, $targetUser);

    expect(fn (): User => resolve(AdminInvitationService::class)->accept('altered-token'))
        ->toThrow(ValidationException::class)
        ->and(Activity::query()->where('event', 'admin.invitation_accepted')
            ->exists())
        ->toBeFalse();
});
