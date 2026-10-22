<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Describes who performed an activity and what it was performed on, by type and id only,
 * with a link when the administrator may open that record. Content is never shown.
 */
trait DescribesActivityLogParties
{
    protected static function causerLabel(ActivityLog $activityLog): string
    {
        if ($activityLog->causer_type === null) {
            return 'Anonymous';
        }

        $causer = $activityLog->causer;

        if ($causer instanceof User) {
            return $causer->name;
        }

        return \sprintf('Deleted %s #%s', Str::lower(Str::headline($activityLog->causer_type)), $activityLog->causer_id);
    }

    protected static function causerUrl(ActivityLog $activityLog): ?string
    {
        $causer = $activityLog->causer;

        return $causer instanceof User ? self::recordUrl($causer) : null;
    }

    protected static function subjectLabel(ActivityLog $activityLog): string
    {
        if ($activityLog->subject_type === null) {
            return 'None';
        }

        $subjectLabel = \sprintf('%s #%s', Str::headline($activityLog->subject_type), $activityLog->subject_id);

        return $activityLog->subject instanceof Model ? $subjectLabel : $subjectLabel.' (deleted)';
    }

    protected static function subjectUrl(ActivityLog $activityLog): ?string
    {
        $subject = $activityLog->subject;

        return $subject instanceof Model ? self::recordUrl($subject) : null;
    }

    /**
     * The detail page of a user, post or live comment the administrator may open.
     */
    private static function recordUrl(Model $model): ?string
    {
        return match (true) {
            $model instanceof User && UserResource::canView($model) => UserResource::getUrl('view', ['record' => $model]),
            $model instanceof Post && PostResource::canView($model) => PostResource::getUrl('view', ['record' => $model]),
            $model instanceof Comment && ! $model->trashed() && CommentResource::canView($model) => CommentResource::getUrl('view', ['record' => $model]),
            default => null,
        };
    }
}
