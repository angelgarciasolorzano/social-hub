<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Services\AnnouncementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const ANNOUNCEMENT_TITLE_SENTINEL = 'centinela-titulo-de-anuncio';
const ANNOUNCEMENT_BODY_SENTINEL = 'centinela-cuerpo-de-anuncio';

/**
 * Resolve the service from the container, as the admin panel does.
 */
function announcementService(): AnnouncementService
{
    return resolve(AnnouncementService::class);
}

/**
 * The announcement factory, typed for static analysis.
 */
function serviceAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Announcement data as the admin form would submit it.
 */
function announcementDataForService(string $title = ANNOUNCEMENT_TITLE_SENTINEL): AnnouncementData
{
    return new AnnouncementData(
        title: $title,
        body: '<p>'.ANNOUNCEMENT_BODY_SENTINEL.'</p>',
        type: AnnouncementType::Warning,
        audience: AnnouncementAudience::Authenticated,
        startsAt: now()->subHour()->toImmutable(),
        endsAt: now()->addDay()->toImmutable(),
        isDismissible: true,
        buttonLabel: null,
        buttonUrl: null,
    );
}

/**
 * Activity log rows recorded for the given admin event.
 *
 * @return array<int, ActivityLog>
 */
function announcementActivities(ActivityLogAdminEvent $activityLogAdminEvent): array
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->get()->all();
}

it('creates an announcement authored by the administrator and audits it', function (): void {
    $user = createUser();

    $announcement = announcementService()->create(announcementDataForService(), $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementCreated);

    expect($announcement->created_by)->toBe($user->id)
        ->and($announcement->type)->toBe(AnnouncementType::Warning)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($announcement))->toBeTrue()
        ->and($activities[0]->causer?->is($user))->toBeTrue();
});

it('records only the names of the changed attributes when updating', function (): void {
    $user = createUser();
    $announcement = announcementService()->create(announcementDataForService(), $user);

    announcementService()->update($announcement, announcementDataForService('Otro título'), $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

    expect($announcement->refresh()->title)->toBe('Otro título')
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->properties?->get('changed_attributes'))->toBe(['title']);
});

it('records nothing when an update changes nothing', function (): void {
    $user = createUser();
    $announcementData = announcementDataForService();
    $announcement = announcementService()->create($announcementData, $user);

    announcementService()->update($announcement->refresh(), $announcementData, $user);

    expect(announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty();
});

it('unpublishes a running announcement by ending it now', function (): void {
    $this->freezeSecond();
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->active()->createOne();

    announcementService()->unpublish($announcement, $user);

    expect($announcement->ends_at?->equalTo(now()))->toBeTrue()
        ->and(Announcement::query()->visibleTo(null)->exists())->toBeFalse()
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUnpublished))->toHaveCount(1);
});

it('refuses to unpublish an announcement that is not running, without writing anything', function (AnnouncementFactory $announcementFactory): void {
    $announcement = $announcementFactory->createOne();
    $endsAt = $announcement->ends_at?->toDateTimeString();

    expect(fn (): Announcement => announcementService()->unpublish($announcement, createUser()))
        ->toThrow(LogicException::class)
        ->and($announcement->refresh()->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUnpublished))->toBeEmpty();
})->with([
    'expired' => fn (): AnnouncementFactory => serviceAnnouncementFactory()->expired(),
    'scheduled' => fn (): AnnouncementFactory => serviceAnnouncementFactory()->scheduled(),
]);

it('duplicates an announcement with its image as an unreviewed copy scheduled for tomorrow', function (): void {
    Storage::fake('public');
    $this->freezeSecond();
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->withButton()->createOne();
    $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

    $copy = announcementService()->duplicate($announcement, $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementDuplicated);

    expect($copy->id)->not->toBe($announcement->id)
        ->and($copy->title)->toBe($announcement->title)
        ->and($copy->button_url)->toBe($announcement->button_url)
        ->and($copy->starts_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($copy->ends_at)->toBeNull()
        ->and($copy->created_by)->toBe($user->id)
        ->and($copy->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->file_name)->toBe('banner.jpg')
        ->and($announcement->refresh()->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($copy))->toBeTrue()
        ->and($activities[0]->properties?->get('source_announcement_id'))->toBe($announcement->id);
});

it('deletes an announcement with its dismissals and keeps its id in the audit', function (): void {
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->createOne();
    $announcement->dismissedBy()->attach(createUser(), ['created_at' => now()]);

    announcementService()->delete($announcement, $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementDeleted);

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeFalse()
        ->and($announcement->dismissedBy()->count())->toBe(0)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject_id)->toBe($announcement->id)
        ->and($activities[0]->subject_type)->toBe(Announcement::MORPH_NAME);
});

it('never stores the title or body of an announcement in the activity log', function (): void {
    Storage::fake('public');
    $user = createUser();
    $announcement = announcementService()->create(announcementDataForService(), $user);
    announcementService()->update($announcement, announcementDataForService(ANNOUNCEMENT_TITLE_SENTINEL.'-editado'), $user);
    $copy = announcementService()->duplicate($announcement, $user);
    announcementService()->unpublish($announcement, $user);
    announcementService()->delete($copy, $user);

    $storedActivities = ActivityLog::query()->get()->toJson();

    expect(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(5)
        ->and($storedActivities)->not->toContain(ANNOUNCEMENT_TITLE_SENTINEL)
        ->and($storedActivities)->not->toContain(ANNOUNCEMENT_BODY_SENTINEL);
});
