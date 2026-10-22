<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Support;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;
use Illuminate\Support\Str;

/**
 * Human-readable names for the stored event and log values, built from the
 * registered event enums so a new enum shows up without extra wiring.
 */
final class ActivityLogEventCatalog
{
    /**
     * The cataloged event stored as the given `event` value, or null when it is no longer cataloged.
     */
    public static function find(string $eventName): ?ActivityLogEvent
    {
        foreach (self::events() as $activityLogEvent) {
            if ($activityLogEvent->eventName() === $eventName) {
                return $activityLogEvent;
            }
        }

        return null;
    }

    /**
     * The readable name of a stored event, or the raw value for an event that is no longer cataloged.
     */
    public static function label(string $eventName): string
    {
        $activityLogEvent = self::find($eventName);

        return $activityLogEvent instanceof ActivityLogEvent
            ? Str::headline($activityLogEvent->name)
            : $eventName;
    }

    /**
     * The readable name of a stored log, or the raw value for an unknown log.
     */
    public static function logLabel(string $logName): string
    {
        $activityLogName = ActivityLogName::tryFrom($logName);

        return $activityLogName instanceof ActivityLogName
            ? Str::headline($activityLogName->name)
            : $logName;
    }

    /**
     * Event options for a select filter, grouped by the readable name of their log.
     *
     * @return array<string, array<string, string>>
     */
    public static function eventOptions(): array
    {
        $eventOptions = [];

        foreach (self::events() as $activityLogEvent) {
            $eventOptions[self::logLabel($activityLogEvent->logName()->value)][$activityLogEvent->eventName()] = Str::headline($activityLogEvent->name);
        }

        return $eventOptions;
    }

    /**
     * Log options for a select filter, keyed by the stored log name.
     *
     * @return array<string, string>
     */
    public static function logOptions(): array
    {
        $logOptions = [];

        foreach (ActivityLogName::cases() as $activityLogName) {
            $logOptions[$activityLogName->value] = Str::headline($activityLogName->name);
        }

        return $logOptions;
    }

    /**
     * Every case of every enum registered in the catalog.
     *
     * @return list<ActivityLogEvent>
     */
    private static function events(): array
    {
        $events = [];

        foreach (ActivityLogEvent::ENUMS as $enumClass) {
            foreach ($enumClass::cases() as $activityLogEvent) {
                $events[] = $activityLogEvent;
            }
        }

        return $events;
    }
}
