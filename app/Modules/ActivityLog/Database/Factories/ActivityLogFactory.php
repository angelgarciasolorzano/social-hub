<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Database\Factories;

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Use the given cataloged event and its log.
     */
    public function forEvent(ActivityLogEvent $activityLogEvent): static
    {
        return $this->state(fn (): array => [
            'log_name' => $activityLogEvent->logName()->value,
            'description' => $activityLogEvent->eventName(),
            'event' => $activityLogEvent->eventName(),
        ]);
    }

    /**
     * Record the given user as the one who performed the action; causers are always users.
     */
    public function causedBy(User $user): static
    {
        return $this->state(fn (): array => [
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->getKey(),
        ]);
    }

    /**
     * Record the given model as the one the action was performed on.
     */
    public function performedOn(Model $model): static
    {
        return $this->state(fn (): array => [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
        ]);
    }
}
