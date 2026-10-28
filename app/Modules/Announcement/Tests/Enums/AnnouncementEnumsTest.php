<?php

declare(strict_types=1);

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;

it('only supports the info, warning and critical types and rejects any other', function (): void {
    expect(array_column(AnnouncementType::cases(), 'value'))->toBe(['info', 'warning', 'critical'])
        ->and(fn (): AnnouncementType => AnnouncementType::from('danger'))->toThrow(ValueError::class);
});

it('only supports the all, authenticated and without two factor audiences and rejects any other', function (): void {
    expect(array_column(AnnouncementAudience::cases(), 'value'))->toBe(['all', 'authenticated', 'without_two_factor'])
        ->and(fn (): AnnouncementAudience => AnnouncementAudience::from('guests'))->toThrow(ValueError::class);
});

it('only supports the toast and dialog displays and rejects any other', function (): void {
    expect(array_column(AnnouncementDisplay::cases(), 'value'))->toBe(['toast', 'dialog'])
        ->and(fn (): AnnouncementDisplay => AnnouncementDisplay::from('popup'))->toThrow(ValueError::class);
});

it('explains when to use each display', function (AnnouncementDisplay $announcementDisplay): void {
    expect($announcementDisplay->description())->not->toBeEmpty();
})->with(AnnouncementDisplay::cases());

it('ranks critical above warning and warning above info', function (): void {
    expect(AnnouncementType::Critical->severity())->toBeGreaterThan(AnnouncementType::Warning->severity())
        ->and(AnnouncementType::Warning->severity())->toBeGreaterThan(AnnouncementType::Info->severity());
});

it('gives every case a readable label', function (AnnouncementType|AnnouncementAudience|AnnouncementDisplay $case, string $label): void {
    expect($case->label())->toBe($label);
})->with([
    'info' => [AnnouncementType::Info, 'Information'],
    'warning' => [AnnouncementType::Warning, 'Warning'],
    'critical' => [AnnouncementType::Critical, 'Critical'],
    'all' => [AnnouncementAudience::All, 'Everyone'],
    'authenticated' => [AnnouncementAudience::Authenticated, 'Signed-in users'],
    'without two factor' => [AnnouncementAudience::WithoutTwoFactor, 'Users without two-factor authentication'],
    'toast' => [AnnouncementDisplay::Toast, 'Toast'],
    'dialog' => [AnnouncementDisplay::Dialog, 'Dialog'],
]);
