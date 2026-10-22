<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Models;

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Policies\ActivityLogPolicy;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * The application's activity record. `activitylog.activity_model` points here, so the package
 * writes and reads through this class and the rest of the app never touches Spatie directly.
 *
 * @mixin IdeHelperActivityLog
 */
#[UseFactory(ActivityLogFactory::class)]
#[UsePolicy(ActivityLogPolicy::class)]
class ActivityLog extends SpatieActivity
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    /**
     * Keep the activities in which the user is the causer or the subject.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function involvingUser(Builder $query, User $user): void
    {
        $query->where(fn (Builder $builder): Builder => $builder
            ->where(fn (Builder $builder): Builder => $builder
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->getKey()))
            ->orWhere(fn (Builder $builder): Builder => $builder
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->getKey())));
    }
}
