<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Services\ActivityLogger;
use Spatie\Activitylog\Support\Config;

it('makes the package build the module model', function (): void {
    expect(Config::activityModelInstance())->toBeInstanceOf(ActivityLog::class);
});

it('keeps the activities in which the user is the causer or the subject', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $activityLogger = resolve(ActivityLogger::class);

    $activityLogger->record(ActivityLogContentEvent::PostCreated, createPost($user), $user);
    $activityLogger->record(ActivityLogAdminEvent::RoleGranted, $user, $otherUser);
    $activityLogger->record(ActivityLogContentEvent::PostCreated, createPost($otherUser), $otherUser);
    $activityLogger->record(ActivityLogAdminEvent::RoleGranted, $otherUser);

    expect(ActivityLog::query()->involvingUser($user)->pluck('event')->sort()->values()->all())
        ->toBe(['admin.role_granted', 'content.post_created']);
});

it('does not match another model that shares the user id', function (): void {
    $user = createUser();
    $post = createPost($user, ['id' => $user->id]);

    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated, $post);

    expect(ActivityLog::query()->involvingUser($user)->exists())->toBeFalse();
});
