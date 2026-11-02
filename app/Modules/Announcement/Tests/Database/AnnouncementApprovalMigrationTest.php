<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run or roll back only the approval workflow migration.
 */
function runAnnouncementApprovalMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_10_30_173738_add_approval_workflow_to_announcements_table.php',
        '--realpath' => true,
    ]);
}

/**
 * Create an announcement with a factory typed for static analysis.
 */
function createApprovalWorkflowAnnouncement(): Announcement
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory->createOne();
}

/**
 * Insert a review row before the review model is introduced.
 */
function insertAnnouncementReview(
    Announcement $announcement,
    AnnouncementReviewAction $announcementReviewAction,
    ?User $user = null,
    ?string $comment = null,
): int {
    return DB::table('announcement_reviews')->insertGetId([
        'announcement_id' => $announcement->id,
        'action' => $announcementReviewAction->value,
        'comment' => $comment,
        'reviewed_by' => $user?->id,
        'created_at' => now(),
    ]);
}

/**
 * Insert a legacy announcement without relying on the model's later status attribute.
 */
function insertAnnouncementForApprovalMigration(): int
{
    return DB::table('announcements')->insertGetId([
        'title' => 'Aviso previo al flujo de aprobación',
        'body' => '<p>Contenido anterior al flujo de aprobación.</p>',
        'type' => 'info',
        'audience' => 'all',
        'starts_at' => now(),
        'ends_at' => null,
        'button_label' => null,
        'button_url' => null,
        'is_dismissible' => true,
        'created_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('defaults existing and new announcements to draft', function (): void {
    try {
        runAnnouncementApprovalMigration('migrate:rollback');
        $existingAnnouncementId = insertAnnouncementForApprovalMigration();

        runAnnouncementApprovalMigration('migrate');
        $newAnnouncementId = insertAnnouncementForApprovalMigration();

        expect(DB::table('announcements')->where('id', $existingAnnouncementId)->value('status'))->toBe('draft')
            ->and(DB::table('announcements')->where('id', $newAnnouncementId)->value('status'))->toBe('draft');
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('stores review actions and comments', function (): void {
    $announcement = createApprovalWorkflowAnnouncement();
    $user = createUser();
    $comment = 'The announcement content is clear.';

    $reviewId = insertAnnouncementReview(
        $announcement,
        AnnouncementReviewAction::Rejected,
        $user,
        $comment,
    );

    expect(DB::table('announcement_reviews')->where('id', $reviewId)->value('action'))->toBe('rejected')
        ->and(DB::table('announcement_reviews')->where('id', $reviewId)->value('comment'))->toBe($comment);
});

it('keeps the review and nulls its reviewer when that user is deleted', function (): void {
    $announcement = createApprovalWorkflowAnnouncement();
    $user = createUser();
    $reviewId = insertAnnouncementReview($announcement, AnnouncementReviewAction::Submitted, $user);

    $user->delete();

    $this->assertDatabaseHas('announcement_reviews', [
        'id' => $reviewId,
        'reviewed_by' => null,
    ]);
});

it('deletes review history when its announcement is deleted', function (): void {
    $announcement = createApprovalWorkflowAnnouncement();
    $reviewId = insertAnnouncementReview($announcement, AnnouncementReviewAction::Submitted);

    $announcement->delete();

    $this->assertDatabaseMissing('announcement_reviews', ['id' => $reviewId]);
});

it('drops only the approval workflow schema on rollback and supports remigration', function (): void {
    try {
        runAnnouncementApprovalMigration('migrate:rollback');

        expect(Schema::hasColumn('announcements', 'status'))->toBeFalse()
            ->and(Schema::hasTable('announcement_reviews'))->toBeFalse()
            ->and(Schema::hasColumns('announcements', ['title', 'display']))->toBeTrue()
            ->and(Schema::hasTable('announcement_dismissals'))->toBeTrue();

        runAnnouncementApprovalMigration('migrate');

        expect(Schema::hasColumn('announcements', 'status'))->toBeTrue()
            ->and(Schema::hasTable('announcement_reviews'))->toBeTrue();
    } finally {
        Artisan::call('migrate:fresh');
    }
});
