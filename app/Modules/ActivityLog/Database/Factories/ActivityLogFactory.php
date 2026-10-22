<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Database\Factories;

use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * Builds activity rows for tests. Application code records activity through ActivityLogger.
 *
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ActivityLog>
     */
    #[Override]
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state: an anonymous event without subject or properties.
     *
     * @return array<string, string|array<string, scalar>|null>
     *
     * @phpstan-return array<model-property<ActivityLog>, mixed>
     */
    public function definition(): array
    {
        $activityLogEvent = ActivityLogContentEvent::PostCreated;

        return [
            'log_name' => $activityLogEvent->logName()->value,
            'description' => $activityLogEvent->eventName(),
            'event' => $activityLogEvent->eventName(),
            'subject_type' => null,
            'subject_id' => null,
            'causer_type' => null,
            'causer_id' => null,
            'attribute_changes' => null,
            'properties' => [],
        ];
    }
}
