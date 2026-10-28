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

it('seeds a running announcement with a working button for users without two-factor', function (): void {
    $this->seed(AnnouncementSeeder::class);

    $announcement = Announcement::query()
        ->running()
        ->where('audience', AnnouncementAudience::WithoutTwoFactor)
        ->whereNotNull('button_url')
        ->sole();

    $this->actingAs(createUser())->get((string) $announcement->button_url)->assertRedirect();
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
