<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Seeders\AnnouncementSeeder;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;

it('seeds scheduled, running and expired examples as approved announcements', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->where('status', AnnouncementStatus::Approved->value)->scheduled()->count())->toBeGreaterThanOrEqual(1)
        ->and(Announcement::query()->where('status', AnnouncementStatus::Approved->value)->running()->count())->toBeGreaterThanOrEqual(1)
        ->and(Announcement::query()->where('status', AnnouncementStatus::Approved->value)->ended()->count())->toBeGreaterThanOrEqual(1);
});

it('seeds all workflow states with matching chronological history and distinct reviewers', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $secondAdministrator = createUser();
    $secondAdministrator->assignRole(AdminRole::Admin->value);

    $this->seed(AnnouncementSeeder::class);

    $draftAnnouncement = Announcement::query()->where('title', AnnouncementSeeder::DRAFT_WORKFLOW_TITLE)->sole();
    $pendingAnnouncement = Announcement::query()->where('title', AnnouncementSeeder::PENDING_REVIEW_WORKFLOW_TITLE)->sole();
    $approvedAnnouncement = Announcement::query()->where('title', AnnouncementSeeder::APPROVED_WORKFLOW_TITLE)->sole();
    $rejectedAnnouncement = Announcement::query()->where('title', AnnouncementSeeder::REJECTED_WORKFLOW_TITLE)->sole();

    expect($draftAnnouncement->status)->toBe(AnnouncementStatus::Draft)
        ->and($draftAnnouncement->reviews)
        ->toBeEmpty()
        ->and($pendingAnnouncement->status)
        ->toBe(AnnouncementStatus::InReview)
        ->and($pendingAnnouncement->reviews->pluck('action')
            ->all())
        ->toBe([AnnouncementReviewAction::Submitted])
        ->and($pendingAnnouncement->reviews->sole()->reviewed_by)
        ->toBe($pendingAnnouncement->created_by)
        ->and($approvedAnnouncement->status)
        ->toBe(AnnouncementStatus::Approved)
        ->and($approvedAnnouncement->reviews->pluck('action')
            ->all())
        ->toBe([AnnouncementReviewAction::Submitted, AnnouncementReviewAction::Approved]);

    $announcementReview = $approvedAnnouncement->reviews()
        ->where('action', AnnouncementReviewAction::Approved->value)
        ->sole();

    expect($announcementReview->reviewed_by)
        ->toBeIn([$user->id, $secondAdministrator->id])
        ->not->toBe($approvedAnnouncement->created_by)
        ->and($announcementReview->comment)
        ->not->toBeNull()
        ->and($rejectedAnnouncement->status)
        ->toBe(AnnouncementStatus::Rejected)
        ->and($rejectedAnnouncement->reviews->pluck('action')
            ->all())
        ->toBe([AnnouncementReviewAction::Submitted, AnnouncementReviewAction::Rejected]);

    $rejection = $rejectedAnnouncement->reviews()
        ->where('action', AnnouncementReviewAction::Rejected->value)
        ->sole();

    expect($rejection->reviewed_by)
        ->toBeIn([$user->id, $secondAdministrator->id])
        ->not->toBe($rejectedAnnouncement->created_by)
        ->and($rejection->comment)
        ->not->toBeNull();
});

it('seeds the running announcement for users without two-factor with a button', function (): void {
    $this->seed(AnnouncementSeeder::class);

    $announcement = Announcement::query()->where('title', AnnouncementSeeder::TWO_FACTOR_TITLE)->sole();

    expect($announcement->isRunning())->toBeTrue()
        ->and($announcement->status)->toBe(AnnouncementStatus::Approved)
        ->and($announcement->audience)->toBe(AnnouncementAudience::WithoutTwoFactor)
        ->and($announcement->button_url)->toBe('/setting/two-factor-authentication');
});

it('shows the announcement for users without two-factor as a dialog and keeps running toasts', function (): void {
    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->where('title', AnnouncementSeeder::TWO_FACTOR_TITLE)->sole()->display)->toBe(AnnouncementDisplay::Dialog)
        ->and(Announcement::query()->running()->where('display', AnnouncementDisplay::Toast)->exists())->toBeTrue();
});

it('shows every critical sample as a dialog and every other one as a toast', function (): void {
    $this->seed(AnnouncementSeeder::class);

    $mismatched = Announcement::query()
        ->get()
        ->filter(fn (Announcement $announcement): bool => ($announcement->type === AnnouncementType::Critical) !== ($announcement->display === AnnouncementDisplay::Dialog));

    expect($mismatched)->toBeEmpty();
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
    $reviewCount = AnnouncementReview::query()->count();

    $this->seed(AnnouncementSeeder::class);

    expect(Announcement::query()->count())->toBe($announcementCount)
        ->and(AnnouncementReview::query()->count())->toBe($reviewCount);
});
