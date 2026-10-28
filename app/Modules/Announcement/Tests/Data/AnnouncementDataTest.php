<?php

declare(strict_types=1);

use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;

/**
 * A complete admin form state, with the given fields replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function announcementFormState(array $overrides = []): array
{
    return [
        'title' => '  Mantenimiento programado  ',
        'body' => '<p>El sitio estará en mantenimiento.</p>',
        'type' => 'warning',
        'audience' => 'authenticated',
        'display' => 'dialog',
        'starts_at' => '2026-11-01 08:00:00',
        'ends_at' => '2026-11-02 08:00:00',
        'is_dismissible' => false,
        'has_button' => true,
        'button_label' => ' Ver detalles ',
        'button_url' => 'https://example.com/estado',
        ...$overrides,
    ];
}

it('builds typed data from the form state and maps it to the model columns', function (): void {
    $announcementData = AnnouncementData::fromFormState(announcementFormState());

    expect($announcementData->toModelAttributes())->toMatchArray([
        'title' => 'Mantenimiento programado',
        'body' => '<p>El sitio estará en mantenimiento.</p>',
        'type' => AnnouncementType::Warning,
        'audience' => AnnouncementAudience::Authenticated,
        'display' => AnnouncementDisplay::Dialog,
        'is_dismissible' => false,
        'button_label' => 'Ver detalles',
        'button_url' => 'https://example.com/estado',
    ])
        ->and($announcementData->startsAt->toDateTimeString())->toBe('2026-11-01 08:00:00')
        ->and($announcementData->endsAt?->toDateTimeString())->toBe('2026-11-02 08:00:00');
});

it('accepts enum cases when the form was filled from an existing announcement', function (): void {
    $announcementData = AnnouncementData::fromFormState(announcementFormState([
        'type' => AnnouncementType::Critical,
        'audience' => AnnouncementAudience::All,
        'display' => AnnouncementDisplay::Toast,
    ]));

    expect($announcementData->type)->toBe(AnnouncementType::Critical)
        ->and($announcementData->audience)->toBe(AnnouncementAudience::All)
        ->and($announcementData->display)->toBe(AnnouncementDisplay::Toast);
});

it('drops the button when has_button is off even if its fields keep a value', function (): void {
    $announcementData = AnnouncementData::fromFormState(announcementFormState(['has_button' => false]));

    expect($announcementData->buttonLabel)->toBeNull()
        ->and($announcementData->buttonUrl)->toBeNull();
});

it('treats a missing end date as an announcement without end and defaults to dismissible', function (): void {
    $state = announcementFormState(['ends_at' => null]);
    unset($state['is_dismissible']);

    $announcementData = AnnouncementData::fromFormState($state);

    expect($announcementData->endsAt)->toBeNull()
        ->and($announcementData->isDismissible)->toBeTrue();
});

it('treats a missing display as a toast', function (): void {
    $state = announcementFormState();
    unset($state['display']);

    expect(AnnouncementData::fromFormState($state)->display)->toBe(AnnouncementDisplay::Toast);
});

it('rejects an unknown type or display, or a missing start date', function (string $field, ?string $value): void {
    expect(fn (): AnnouncementData => AnnouncementData::fromFormState(announcementFormState([$field => $value])))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'unknown type' => ['type', 'danger'],
    'missing start date' => ['starts_at', null],
    'unknown display' => ['display', 'popup'],
]);
