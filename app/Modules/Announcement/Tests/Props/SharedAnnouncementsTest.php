<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function sharedAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Count the queries that read announcements or their media while rendering the home page.
 */
function countAnnouncementQueriesOnHome(TestCase $testCase): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->get(route('home'))->assertOk();

    $announcementQueries = array_filter(
        DB::getQueryLog(),
        fn (array $query): bool => str_contains($query['query'], 'announcement')
            || str_contains($query['query'], '`media`'),
    );
    DB::disableQueryLog();

    return count($announcementQueries);
}

it('shares only the announcements for everyone with a guest on the login page', function (): void {
    $announcement = sharedAnnouncementFactory()->forAudience(AnnouncementAudience::All)->createOne();
    sharedAnnouncementFactory()->forAudience(AnnouncementAudience::Authenticated)->createOne();
    sharedAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne();
    sharedAnnouncementFactory()->expired()->createOne();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('announcements', 1)
            ->where('announcements.0.id', $announcement->id)
            ->has('announcements.0', fn (Assert $assert): Assert => $assert
                ->hasAll(['id', 'title', 'bodyHtml', 'type', 'display', 'buttonLabel', 'buttonUrl', 'isDismissible', 'imageUrl'])
            )
        );
});

it('shares the announcements for users without two-factor with a signed-in user who lacks it', function (): void {
    $announcement = sharedAnnouncementFactory()
        ->ofType(AnnouncementType::Info)
        ->forAudience(AnnouncementAudience::All)
        ->createOne(['starts_at' => now()->subDay()]);
    $forWithoutTwoFactor = sharedAnnouncementFactory()
        ->ofType(AnnouncementType::Info)
        ->forAudience(AnnouncementAudience::WithoutTwoFactor)
        ->createOne(['starts_at' => now()->subHour()]);

    $this->actingAs(createUser())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('announcements', 2)
            ->where('announcements.0.id', $forWithoutTwoFactor->id)
            ->where('announcements.1.id', $announcement->id)
        );
});

it('does not share an announcement the user dismissed', function (): void {
    $user = createUser();
    $announcement = sharedAnnouncementFactory()->createOne();
    $announcement->dismissedBy()->attach($user, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('announcements', 0));
});

it('reads the announcements with a fixed number of queries however many there are', function (): void {
    Storage::fake('public');
    /** @var TestCase $testCase */
    $testCase = $this;
    $testCase->actingAs(createUser());

    $announcement = sharedAnnouncementFactory()->createOne();
    $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

    countAnnouncementQueriesOnHome($testCase);
    $queriesWithOneAnnouncement = countAnnouncementQueriesOnHome($testCase);

    foreach (range(1, 2) as $index) {
        sharedAnnouncementFactory()->createOne()->addMedia(UploadedFile::fake()->image('banner.jpg'))
            ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);
    }

    expect($queriesWithOneAnnouncement)->toBeGreaterThan(0)
        ->and(countAnnouncementQueriesOnHome($testCase))->toBe($queriesWithOneAnnouncement);
});
