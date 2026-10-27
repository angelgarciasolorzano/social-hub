<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function dismissAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Dismiss the given announcement as the given user.
 *
 * @return TestResponse<Response>
 */
function dismissAnnouncementAs(TestCase $testCase, User $user, Announcement $announcement): TestResponse
{
    return $testCase->actingAs($user)->postJson(route('announcements.dismiss', $announcement));
}

/**
 * How many dismissals the given announcement has.
 */
function announcementDismissalCount(Announcement $announcement): int
{
    return $announcement->dismissedBy()->count();
}

it('dismisses an announcement so the user no longer receives it, while others still do', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $announcement = dismissAnnouncementFactory()->createOne();

    dismissAnnouncementAs($this, $user, $announcement)
        ->assertOk()
        ->assertExactJson(['dismissed' => true]);

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('announcements', 0));
    $this->actingAs($otherUser)->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('announcements', 1));
});

it('keeps a single dismissal when the request is repeated', function (): void {
    $user = createUser();
    $announcement = dismissAnnouncementFactory()->createOne();

    dismissAnnouncementAs($this, $user, $announcement)->assertOk();
    dismissAnnouncementAs($this, $user, $announcement)->assertOk();

    expect(announcementDismissalCount($announcement))->toBe(1);
});

it('rejects a guest without dismissing anything', function (): void {
    $announcement = dismissAnnouncementFactory()->createOne();

    $this->postJson(route('announcements.dismiss', $announcement))->assertUnauthorized();

    expect(announcementDismissalCount($announcement))->toBe(0);
});

it('forbids dismissing an announcement the user cannot dismiss', function (AnnouncementFactory $announcementFactory): void {
    $announcement = $announcementFactory->createOne();

    dismissAnnouncementAs($this, createUserWithTwoFactor(), $announcement)->assertForbidden();

    expect(announcementDismissalCount($announcement))->toBe(0);
})->with([
    'not dismissible' => fn (): AnnouncementFactory => dismissAnnouncementFactory()->notDismissible(),
    'expired' => fn (): AnnouncementFactory => dismissAnnouncementFactory()->expired(),
    'scheduled' => fn (): AnnouncementFactory => dismissAnnouncementFactory()->scheduled(),
    'for another audience' => fn (): AnnouncementFactory => dismissAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor),
]);

it('answers 404 for an announcement that does not exist', function (): void {
    $this->actingAs(createUser())
        ->postJson(route('announcements.dismiss', ['announcement' => 999999]))
        ->assertNotFound();
});

it('allows 20 dismissals per minute and then answers 429 with Retry-After', function (): void {
    $user = createUser();
    $announcement = dismissAnnouncementFactory()->createOne();

    foreach (range(1, 20) as $attempt) {
        dismissAnnouncementAs($this, $user, $announcement)->assertOk();
    }

    dismissAnnouncementAs($this, $user, $announcement)
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
