<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Models\Announcement;
use Carbon\CarbonImmutable;

/**
 * The announcement factory, typed for static analysis.
 */
function overlapAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The ids of the dialogs that cross the given schedule.
 *
 * @return list<int>
 */
function overlappingDialogIds(string $startsAt, ?string $endsAt, ?int $exceptId = null): array
{
    return array_values(Announcement::query()
        ->dialogsOverlapping(CarbonImmutable::parse($startsAt), $endsAt === null ? null : CarbonImmutable::parse($endsAt), $exceptId)
        ->orderBy('id')
        ->get()
        ->map(fn (Announcement $announcement): int => $announcement->id)
        ->all());
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00'));
});

it('finds a dialog whose schedule crosses the given one', function (): void {
    $announcement = overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-10-28 10:00:00', 'ends_at' => '2026-10-30 10:00:00']);

    expect(overlappingDialogIds('2026-10-29 10:00:00', '2026-11-02 10:00:00'))->toBe([$announcement->id]);
});

it('ignores a dialog that ends before the given schedule starts or starts after it ends', function (): void {
    overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-10-28 10:00:00', 'ends_at' => '2026-10-29 10:00:00']);
    overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-11-05 10:00:00', 'ends_at' => '2026-11-06 10:00:00']);

    expect(overlappingDialogIds('2026-10-30 10:00:00', '2026-11-02 10:00:00'))->toBeEmpty();
});

it('treats a missing end on either side as running forever', function (): void {
    $withoutEnd = overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-10-20 10:00:00', 'ends_at' => null]);
    $announcement = overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-02 10:00:00']);

    expect(overlappingDialogIds('2026-11-01 10:00:00', null))->toBe([$withoutEnd->id, $announcement->id]);
});

it('never counts toasts, ended dialogs or the announcement being edited', function (): void {
    overlapAnnouncementFactory()->createOne(['starts_at' => '2026-10-28 10:00:00', 'ends_at' => null]);
    overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-10-01 10:00:00', 'ends_at' => '2026-10-28 11:00:00']);
    $announcement = overlapAnnouncementFactory()->asDialog()->createOne(['starts_at' => '2026-10-28 10:00:00', 'ends_at' => null]);

    expect(overlappingDialogIds('2026-10-01 00:00:00', null, exceptId: $announcement->id))->toBeEmpty()
        ->and(overlappingDialogIds('2026-10-01 00:00:00', null))->toBe([$announcement->id]);
});
