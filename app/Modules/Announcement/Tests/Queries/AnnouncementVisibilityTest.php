<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use App\User\Modules\TwoFactor\Enums\TwoFactorStatus;

/**
 * The announcement factory, typed for static analysis.
 */
function visibilityAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The ids of the announcements the given visitor sees, in display order.
 *
 * @return list<int>
 */
function visibleAnnouncementIds(?User $user): array
{
    return array_values(Announcement::query()
        ->visibleTo($user)
        ->get()
        ->map(fn (Announcement $announcement): int => $announcement->id)
        ->all());
}

/**
 * A user whose two-factor setup is in the given state.
 */
function createUserWithTwoFactorStatus(TwoFactorStatus $twoFactorStatus): User
{
    /** @var UserFactory $factory */
    $factory = User::factory();

    return match ($twoFactorStatus) {
        TwoFactorStatus::Disabled => $factory->createOne(),
        TwoFactorStatus::Pending => $factory->twoFactorPending()->createOne(),
        TwoFactorStatus::Confirmed => $factory->twoFactorConfirmed()->createOne(),
    };
}

it('shows a guest only the active announcements meant for everyone', function (): void {
    $announcement = visibilityAnnouncementFactory()->active()->createOne();
    visibilityAnnouncementFactory()->scheduled()->createOne();
    visibilityAnnouncementFactory()->expired()->createOne();
    visibilityAnnouncementFactory()->active()->forAudience(AnnouncementAudience::Authenticated)->createOne();
    visibilityAnnouncementFactory()->active()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne();

    expect(visibleAnnouncementIds(null))->toBe([$announcement->id]);
});

it('shows an announcement from its start until just before its end, and forever without an end', function (): void {
    $this->freezeTime();
    $announcement = visibilityAnnouncementFactory()->createOne(['starts_at' => now(), 'ends_at' => now()->addHour()]);
    $withoutEnd = visibilityAnnouncementFactory()->createOne(['starts_at' => now()->subYear(), 'ends_at' => null]);
    visibilityAnnouncementFactory()->createOne(['starts_at' => now()->subDay(), 'ends_at' => now()]);

    expect(visibleAnnouncementIds(null))->toEqualCanonicalizing([$announcement->id, $withoutEnd->id]);
});

it('shows each audience only to the users it targets', function (TwoFactorStatus $twoFactorStatus, bool $seesWithoutTwoFactor): void {
    $user = createUserWithTwoFactorStatus($twoFactorStatus);
    $announcement = visibilityAnnouncementFactory()->forAudience(AnnouncementAudience::All)->createOne();
    $forSignedIn = visibilityAnnouncementFactory()->forAudience(AnnouncementAudience::Authenticated)->createOne();
    $forWithoutTwoFactor = visibilityAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne();

    $expectedIds = [$announcement->id, $forSignedIn->id];

    if ($seesWithoutTwoFactor) {
        $expectedIds[] = $forWithoutTwoFactor->id;
    }

    expect(visibleAnnouncementIds($user))->toEqualCanonicalizing($expectedIds);
})->with([
    'without two-factor' => [TwoFactorStatus::Disabled, true],
    'with pending two-factor' => [TwoFactorStatus::Pending, true],
    'with confirmed two-factor' => [TwoFactorStatus::Confirmed, false],
]);

it('hides a dismissed announcement only from the user who dismissed it', function (): void {
    $announcement = visibilityAnnouncementFactory()->createOne();
    $dismissingUser = createUser();
    $otherUser = createUser();
    $announcement->dismissedBy()->attach($dismissingUser, ['created_at' => now()]);

    expect(visibleAnnouncementIds($dismissingUser))
        ->toBeEmpty()
        ->and(visibleAnnouncementIds($otherUser))->toBe([$announcement->id])
        ->and(visibleAnnouncementIds(null))->toBe([$announcement->id]);
});

it('shows at most three announcements, the most severe and then the most recent first', function (): void {
    $announcement = visibilityAnnouncementFactory()->ofType(AnnouncementType::Info)->createOne(['starts_at' => now()->subDays(3)]);
    $recentInfo = visibilityAnnouncementFactory()->ofType(AnnouncementType::Info)->createOne(['starts_at' => now()->subDay()]);
    $warning = visibilityAnnouncementFactory()->ofType(AnnouncementType::Warning)->createOne(['starts_at' => now()->subDays(5)]);
    $critical = visibilityAnnouncementFactory()->ofType(AnnouncementType::Critical)->createOne(['starts_at' => now()->subDays(9)]);

    expect(visibleAnnouncementIds(null))
        ->toHaveCount(Announcement::MAX_VISIBLE)
        ->toBe([$critical->id, $warning->id, $recentInfo->id])
        ->not->toContain($announcement->id);
});
