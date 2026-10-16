<?php

declare(strict_types=1);

use App\Modules\Admin\Mail\AdminInvitationMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::name('admin.invitations.accept')->group(function (): void {
        Route::get(
            '/testing/admin-invitations/{token}',
            static fn (string $token): string => $token,
        );
    });
});

it('renders the invitation link and expiry in HTML and plain text', function (): void {
    $plainToken = 'single-use-invitation-token';
    $expiresAt = CarbonImmutable::parse('2026-10-23 14:30:00');
    $acceptUrl = route('admin.invitations.accept', ['token' => $plainToken]);
    $adminInvitationMail = new AdminInvitationMail($plainToken, $expiresAt);

    $adminInvitationMail
        ->assertHasSubject('You have been invited to Social Hub Admin')
        ->assertSeeInHtml($acceptUrl)
        ->assertSeeInHtml('Accept invitation')
        ->assertSeeInHtml($expiresAt->toDayDateTimeString())
        ->assertSeeInText($acceptUrl)
        ->assertSeeInText('Accept invitation')
        ->assertSeeInText($expiresAt->toDayDateTimeString());
});
