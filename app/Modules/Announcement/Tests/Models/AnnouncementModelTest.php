<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;

/**
 * The announcement factory, typed for static analysis.
 */
function announcementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

it('creates a dismissible announcement for everyone that is active now by default', function (): void {
    $announcement = announcementFactory()->createOne();

    expect($announcement->audience)->toBe(AnnouncementAudience::All)
        ->and($announcement->is_dismissible)->toBeTrue()
        ->and($announcement->button_label)->toBeNull()
        ->and($announcement->starts_at->isPast())->toBeTrue()
        ->and($announcement->ends_at?->isFuture())->toBeTrue()
        ->and($announcement->creator)->not->toBeNull();
});

it('creates each state with coherent dates', function (AnnouncementFactory $announcementFactory, bool $hasStarted, bool $hasEnded): void {
    $announcement = $announcementFactory->createOne()->refresh();

    expect($announcement->starts_at->isPast())->toBe($hasStarted)
        ->and($announcement->ends_at?->isPast())->toBe($hasEnded)
        ->and($announcement->ends_at?->greaterThan($announcement->starts_at))->toBeTrue();
})->with([
    'scheduled' => [fn (): AnnouncementFactory => announcementFactory()->scheduled(), false, false],
    'active' => [fn (): AnnouncementFactory => announcementFactory()->active(), true, false],
    'expired' => [fn (): AnnouncementFactory => announcementFactory()->expired(), true, true],
]);

it('applies the button, dismissible, audience, type and author states', function (): void {
    $user = createUser();

    $announcement = announcementFactory()
        ->withButton()
        ->notDismissible()
        ->forAudience(AnnouncementAudience::WithoutTwoFactor)
        ->ofType(AnnouncementType::Critical)
        ->createdBy($user)
        ->createOne()
        ->refresh();

    expect($announcement->button_label)->not->toBeNull()
        ->and($announcement->button_url)->toStartWith('http')
        ->and($announcement->is_dismissible)->toBeFalse()
        ->and($announcement->audience)->toBe(AnnouncementAudience::WithoutTwoFactor)
        ->and($announcement->type)->toBe(AnnouncementType::Critical)
        ->and($announcement->creator?->is($user))->toBeTrue();
});

it('lists only the users who dismissed the announcement', function (): void {
    $announcement = announcementFactory()->createOne();
    $otherAnnouncement = announcementFactory()->createOne();
    $dismissingUser = createUser();
    $otherUser = createUser();
    $announcement->dismissedBy()->attach($dismissingUser, ['created_at' => now()]);
    $otherAnnouncement->dismissedBy()->attach($otherUser, ['created_at' => now()]);

    expect($announcement->dismissedBy()->pluck('users.id')->all())->toBe([$dismissingUser->id]);
});

it('returns no creator once the author is deleted', function (): void {
    $user = createUser();
    $announcement = announcementFactory()->createdBy($user)->createOne();

    $user->delete();

    expect($announcement->refresh()->creator)->toBeNull()
        ->and($announcement->created_by)->toBeNull();
});
