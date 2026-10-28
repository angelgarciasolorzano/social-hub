<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run or roll back only the migration that adds the display of an announcement.
 */
function runAnnouncementDisplayMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_10_28_000000_add_display_to_announcements_table.php',
        '--realpath' => true,
    ]);
}

/**
 * Insert an announcement row as it was stored before the display column existed.
 */
function insertAnnouncementWithoutDisplay(): int
{
    return DB::table('announcements')->insertGetId([
        'title' => 'Mantenimiento programado',
        'body' => '<p>El sitio estará en mantenimiento.</p>',
        'type' => 'info',
        'audience' => 'all',
        'starts_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('shows the announcements that existed before the migration as toasts', function (): void {
    try {
        runAnnouncementDisplayMigration('migrate:rollback');
        $announcementId = insertAnnouncementWithoutDisplay();

        runAnnouncementDisplayMigration('migrate');

        expect(DB::table('announcements')->where('id', $announcementId)->value('display'))->toBe('toast');
    } finally {
        Artisan::call('migrate:fresh');
    }
});

it('drops only the display column on rollback', function (): void {
    try {
        runAnnouncementDisplayMigration('migrate:rollback');

        expect(Schema::hasColumn('announcements', 'display'))->toBeFalse()
            ->and(Schema::hasColumns('announcements', ['title', 'type', 'audience', 'is_dismissible']))->toBeTrue()
            ->and(Schema::hasTable('announcement_dismissals'))->toBeTrue();
    } finally {
        Artisan::call('migrate:fresh');
    }
});
