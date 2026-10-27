<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run or roll back only the migration that creates the announcement tables.
 */
function runAnnouncementMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_10_27_000000_create_announcements_tables.php',
        '--realpath' => true,
    ]);
}

/**
 * Insert an announcement row directly; the model arrives in a later task.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertAnnouncement(array $attributes = []): int
{
    return DB::table('announcements')->insertGetId([
        'title' => 'Mantenimiento programado',
        'body' => '<p>El sitio estará en mantenimiento.</p>',
        'type' => 'info',
        'audience' => 'all',
        'starts_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

/**
 * Insert a dismissal of the given announcement by the given user.
 */
function insertAnnouncementDismissal(int $announcementId, User $user): bool
{
    return DB::table('announcement_dismissals')->insert([
        'announcement_id' => $announcementId,
        'user_id' => $user->id,
        'created_at' => now(),
    ]);
}

it('drops both tables on rollback and recreates them with their indexes', function (): void {
    try {
        runAnnouncementMigration('migrate:rollback');

        expect(Schema::hasTable('announcements'))->toBeFalse()
            ->and(Schema::hasTable('announcement_dismissals'))->toBeFalse();

        runAnnouncementMigration('migrate');

        expect(Schema::hasIndex('announcements', ['starts_at', 'ends_at']))->toBeTrue()
            ->and(Schema::hasIndex('announcement_dismissals', ['announcement_id', 'user_id'], 'unique'))->toBeTrue();
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('makes announcements dismissible by default', function (): void {
    $announcementId = insertAnnouncement();

    expect(DB::table('announcements')->where('id', $announcementId)->value('is_dismissible'))->toBe(1);
});

it('rejects a second dismissal of the same announcement by the same user', function (): void {
    $user = createUser();
    $announcementId = insertAnnouncement();
    insertAnnouncementDismissal($announcementId, $user);

    expect(fn (): bool => insertAnnouncementDismissal($announcementId, $user))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('deletes the dismissals together with the announcement or the user', function (): void {
    $user = createUser();
    $deletedAnnouncementId = insertAnnouncement();
    $keptAnnouncementId = insertAnnouncement();
    insertAnnouncementDismissal($deletedAnnouncementId, createUser());
    insertAnnouncementDismissal($keptAnnouncementId, $user);

    DB::table('announcements')->where('id', $deletedAnnouncementId)->delete();
    $user->delete('*');

    expect(DB::table('announcement_dismissals')->count())->toBe(0);
});

it('keeps the announcement when its author is deleted', function (): void {
    $user = createUser();
    $announcementId = insertAnnouncement(['created_by' => $user->id]);

    $user->delete('*');

    expect(DB::table('announcements')->where('id', $announcementId)->exists())->toBeTrue()
        ->and(DB::table('announcements')->where('id', $announcementId)->value('created_by'))->toBeNull();
});
