<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Requests\AnnouncementDismissalSyncRequest;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function syncAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Send the announcements a guest closed in this browser, as the given user right after signing in.
 *
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function syncDismissalsAs(TestCase $testCase, User $user, array $payload): TestResponse
{
    return $testCase->actingAs($user)->postJson(route('announcements.dismissals.sync'), $payload);
}

/**
 * The ids of the announcements the given user has dismissed.
 *
 * @return list<int>
 */
function dismissedAnnouncementIdsOf(User $user): array
{
    return array_values(Announcement::query()
        ->whereHas('dismissedBy', fn (Builder $builder): Builder => $builder->whereKey($user->id))
        ->orderBy('id')
        ->get()
        ->map(fn (Announcement $announcement): int => $announcement->id)
        ->all());
}

/**
 * A request body that breaks the given validation rule.
 *
 * @return array<string, mixed>
 */
function invalidDismissalSyncPayload(string $invalidCase): array
{
    return match ($invalidCase) {
        'missing ids' => [],
        'empty list' => ['announcement_ids' => []],
        'not a list' => ['announcement_ids' => 'nope'],
        'repeated id' => ['announcement_ids' => [1, 1]],
        'non numeric id' => ['announcement_ids' => ['x']],
        'zero id' => ['announcement_ids' => [0]],
        'too many ids' => ['announcement_ids' => range(1, AnnouncementDismissalSyncRequest::MAX_ANNOUNCEMENT_IDS + 1)],
        default => throw new InvalidArgumentException("Unknown invalid case {$invalidCase}."),
    };
}

it('stores only the announcements the user may still dismiss and answers their ids', function (): void {
    $user = createUserWithTwoFactor();
    $announcement = syncAnnouncementFactory()->createOne();
    $notDismissible = syncAnnouncementFactory()->notDismissible()->createOne();
    $expired = syncAnnouncementFactory()->expired()->createOne();
    $otherAudience = syncAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne();

    syncDismissalsAs($this, $user, [
        'announcement_ids' => [$announcement->id, $notDismissible->id, $expired->id, $otherAudience->id, 999999],
    ])
        ->assertOk()
        ->assertExactJson(['dismissed' => [$announcement->id]]);

    expect(dismissedAnnouncementIdsOf($user))->toBe([$announcement->id]);
});

it('keeps a single dismissal when the sync is repeated or the announcement was already dismissed', function (): void {
    $user = createUser();
    $announcement = syncAnnouncementFactory()->createOne();
    $announcement->dismissedBy()->attach($user, ['created_at' => now()]);

    syncDismissalsAs($this, $user, ['announcement_ids' => [$announcement->id]])->assertOk();
    syncDismissalsAs($this, $user, ['announcement_ids' => [$announcement->id]])
        ->assertOk()
        ->assertExactJson(['dismissed' => [$announcement->id]]);

    expect(dismissedAnnouncementIdsOf($user))->toBe([$announcement->id]);
});

it('rejects a guest without storing anything', function (): void {
    $announcement = syncAnnouncementFactory()->createOne();

    $this->postJson(route('announcements.dismissals.sync'), ['announcement_ids' => [$announcement->id]])
        ->assertUnauthorized();

    expect(DB::table('announcement_dismissals')->count())->toBe(0);
});

it('rejects an invalid list of ids without storing anything', function (string $invalidCase): void {
    syncAnnouncementFactory()->createOne();

    syncDismissalsAs($this, createUser(), invalidDismissalSyncPayload($invalidCase))
        ->assertUnprocessable();

    expect(DB::table('announcement_dismissals')->count())->toBe(0);
})->with(['missing ids', 'empty list', 'not a list', 'repeated id', 'non numeric id', 'zero id', 'too many ids']);

it('allows 10 syncs per minute and then answers 429 with Retry-After', function (): void {
    $user = createUser();
    $announcement = syncAnnouncementFactory()->createOne();

    foreach (range(1, 10) as $attempt) {
        syncDismissalsAs($this, $user, ['announcement_ids' => [$announcement->id]])->assertOk();
    }

    syncDismissalsAs($this, $user, ['announcement_ids' => [$announcement->id]])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
