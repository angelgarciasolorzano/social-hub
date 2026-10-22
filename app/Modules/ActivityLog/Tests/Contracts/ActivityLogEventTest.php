<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Every case of every enum registered in the catalog.
 *
 * @return list<ActivityLogEvent>
 */
function registeredActivityLogEvents(): array
{
    return array_merge(...array_map(
        fn (string $enumClass): array => $enumClass::cases(),
        ActivityLogEvent::ENUMS,
    ));
}

arch('every event enum implements the activity log event contract')
    ->expect('App\Modules\ActivityLog\Enums\Events')
    ->toBeEnums()
    ->toImplement(ActivityLogEvent::class);

test('every event enum is registered in the catalog', function (): void {
    $enumClasses = array_map(
        fn (SplFileInfo $splFileInfo): string => 'App\Modules\ActivityLog\Enums\Events\\'.$splFileInfo->getFilenameWithoutExtension(),
        File::files(app_path('Modules/ActivityLog/Enums/Events')),
    );

    expect(ActivityLogEvent::ENUMS)->toEqualCanonicalizing($enumClasses);
});

test('event names are unique across the catalog', function (): void {
    $eventNames = array_map(
        fn (ActivityLogEvent $activityLogEvent): string => $activityLogEvent->eventName(),
        registeredActivityLogEvents(),
    );

    expect($eventNames)->toHaveSameSize(array_unique($eventNames));
});

test('every log has at least one event', function (): void {
    $usedLogNames = array_map(
        fn (ActivityLogEvent $activityLogEvent): string => $activityLogEvent->logName()->value,
        registeredActivityLogEvents(),
    );

    expect(array_values(array_unique($usedLogNames)))->toEqualCanonicalizing(array_column(ActivityLogName::cases(), 'value'));
});

test('every log has a positive retention', function (ActivityLogName $activityLogName): void {
    expect($activityLogName->retentionDays())->toBeGreaterThan(0);
})->with(ActivityLogName::cases());
