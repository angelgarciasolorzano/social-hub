<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Database\Factories\AnnouncementReviewFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use Carbon\CarbonImmutable;

/**
 * The announcement factory, typed for static analysis.
 */
function announcementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The review factory, typed for static analysis.
 */
function announcementReviewFactory(): AnnouncementReviewFactory
{
    /** @var AnnouncementReviewFactory $factory */
    $factory = AnnouncementReview::factory();

    return $factory;
}

it('reads a new announcement status as draft', function (): void {
    $announcement = announcementFactory()->createOne()->refresh();

    expect($announcement->status)->toBe(AnnouncementStatus::Draft);
});

it('casts each persisted review action to its enum', function (AnnouncementReviewAction $announcementReviewAction): void {
    $announcementReview = announcementReviewFactory()->createOne(['action' => $announcementReviewAction])->refresh();

    expect($announcementReview->action)->toBe($announcementReviewAction)
        ->and($announcementReview->created_at)->toBeInstanceOf(CarbonImmutable::class);
})->with(AnnouncementReviewAction::cases());

it('returns the announcement and user associated with a review', function (): void {
    $announcement = announcementFactory()->createOne();
    $user = createUser();
    $announcementReview = announcementReviewFactory()
        ->for($announcement, 'announcement')
        ->createOne(['reviewed_by' => $user->id]);

    expect($announcementReview->announcement->is($announcement))->toBeTrue()
        ->and($announcementReview->reviewedBy?->is($user))->toBeTrue();
});

it('returns review history by creation time and then id', function (): void {
    $announcement = announcementFactory()->createOne();
    $announcementReview = announcementReviewFactory()->for($announcement, 'announcement')->createOne([
        'created_at' => CarbonImmutable::parse('2026-10-03 12:00:00'),
    ]);
    $firstSameTimeReview = announcementReviewFactory()->for($announcement, 'announcement')->createOne([
        'created_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
    ]);
    $secondSameTimeReview = announcementReviewFactory()->for($announcement, 'announcement')->createOne([
        'created_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
    ]);

    expect($announcement->reviews()->pluck('id')->all())->toBe([
        $firstSameTimeReview->id,
        $secondSameTimeReview->id,
        $announcementReview->id,
    ]);
});

it('returns no review actor after that user is deleted', function (): void {
    $user = createUser();
    $announcementReview = announcementReviewFactory()->createOne(['reviewed_by' => $user->id]);

    $user->delete();

    expect($announcementReview->refresh()->reviewedBy)->toBeNull();
});

it('creates a dismissible toast for everyone that is active now by default', function (): void {
    $announcement = announcementFactory()->createOne();

    expect($announcement->audience)->toBe(AnnouncementAudience::All)
        ->and($announcement->display)->toBe(AnnouncementDisplay::Toast)
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
        ->and($announcement->button_url)->toStartWith('https://')
        ->and($announcement->is_dismissible)->toBeFalse()
        ->and($announcement->audience)->toBe(AnnouncementAudience::WithoutTwoFactor)
        ->and($announcement->type)->toBe(AnnouncementType::Critical)
        ->and($announcement->creator?->is($user))->toBeTrue();
});

it('shows an announcement as a dialog with the asDialog state', function (): void {
    expect(announcementFactory()->asDialog()->createOne()->refresh()->display)->toBe(AnnouncementDisplay::Dialog);
});

it('reads an announcement created without display as a toast', function (): void {
    $attributes = announcementFactory()->makeOne()->only(['title', 'body', 'type', 'audience', 'starts_at', 'ends_at', 'is_dismissible']);

    expect(Announcement::query()->create($attributes)->refresh()->display)->toBe(AnnouncementDisplay::Toast);
});

it('rejects a display outside the enum', function (): void {
    $announcement = announcementFactory()->createOne();

    expect(fn (): bool => $announcement->forceFill(['display' => 'popup'])->save())->toThrow(ValueError::class);
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
