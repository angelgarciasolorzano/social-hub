<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;

it('finds the cataloged event behind a stored event value', function (): void {
    expect(ActivityLogEventCatalog::find('auth.password_updated'))->toBe(ActivityLogAuthEvent::PasswordUpdated)
        ->and(ActivityLogEventCatalog::find('legacy.unknown'))->toBeNull();
});

it('labels a stored event with its readable name', function (): void {
    expect(ActivityLogEventCatalog::label('auth.password_updated'))->toBe('Password Updated')
        ->and(ActivityLogEventCatalog::label('admin.role_permission_granted'))->toBe('Role Permission Granted');
});

it('falls back to the raw value for an event that is no longer cataloged', function (): void {
    expect(ActivityLogEventCatalog::label('legacy.unknown'))->toBe('legacy.unknown');
});

it('labels a stored log and falls back for an unknown one', function (): void {
    expect(ActivityLogEventCatalog::logLabel('trusted_devices'))->toBe('Trusted Devices')
        ->and(ActivityLogEventCatalog::logLabel('legacy'))->toBe('legacy');
});

it('offers one event option per cataloged event, grouped by log', function (): void {
    $eventOptions = ActivityLogEventCatalog::eventOptions();
    $catalogedEventCount = array_sum(array_map(
        fn (string $enumClass): int => count($enumClass::cases()),
        ActivityLogEvent::ENUMS,
    ));

    expect(array_keys($eventOptions))->toEqualCanonicalizing(array_values(ActivityLogEventCatalog::logOptions()))
        ->and(array_sum(array_map(count(...), $eventOptions)))->toBe($catalogedEventCount)
        ->and($eventOptions['Auth']['auth.login'])->toBe('User Logged In');
});

it('offers one log option per log', function (): void {
    expect(ActivityLogEventCatalog::logOptions())->toHaveSameSize(ActivityLogName::cases())
        ->toHaveKey('trusted_devices', 'Trusted Devices');
});
