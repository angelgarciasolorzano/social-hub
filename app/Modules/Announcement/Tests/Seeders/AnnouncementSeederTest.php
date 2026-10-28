<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Seeders\AnnouncementSeeder;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Models\Announcement;

it('seeds at least one scheduled, one running and one expired announcement', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->scheduled()->count())->toBeGreaterThanOrEqual(1)
        ->and(Announcement::query()->running()->count())->toBeGreaterThanOrEqual(1)
        ->and(Announcement::query()->ended()->count())->toBeGreaterThanOrEqual(1);
});

it('seeds the running announcement for users without two-factor with a button', function (): void {
    $this->seed(AnnouncementSeeder::class);

    $announcement = Announcement::query()->where('title', AnnouncementSeeder::TWO_FACTOR_TITLE)->sole();

    expect($announcement->isRunning())->toBeTrue()
        ->and($announcement->audience)->toBe(AnnouncementAudience::WithoutTwoFactor)
        ->and($announcement->button_url)->toBe('/setting/two-factor-authentication');
});

it('points every internal button to an existing page', function (): void {
    $this->seed(AnnouncementSeeder::class);
    $this->actingAs(createUser());

    $announcements = Announcement::query()->where('button_url', 'like', '/%')->get();

    expect($announcements)->not->toBeEmpty();

    foreach ($announcements as $announcement) {
        expect($this->get((string) $announcement->button_url)->status())->not->toBe(404);
    }
});

it('seeds a running announcement that users cannot dismiss', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->running()->where('is_dismissible', false)->exists())->toBeTrue();
});

it('credits the seeded announcements to an administrator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->whereNot('created_by', $user->id)->exists())->toBeFalse();
});

it('seeds announcements without author when no administrator exists', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->whereNotNull('created_by')->exists())->toBeFalse();
});

it('records nothing in the activity log', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('does nothing when announcements already exist', function (): void {
    $this->seed(AnnouncementSeeder::class);
    $announcementCount = Announcement::query()->count();

    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->count())->toBe($announcementCount);
});
