<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

/**
 * Register a routed endpoint that records a like on the given post, so the logger sees a real HTTP request.
 */
function registerActivityLoggerProbeRoute(Post $post): string
{
    Route::middleware('web')->get('/activity-logger-probe', function () use ($post): string {
        resolve(ActivityLogger::class)->record(ActivityLogLikeEvent::Added, $post);

        return 'ok';
    });

    return '/activity-logger-probe';
}

it('stores the log name and event of the recorded event', function (): void {
    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated);

    expect(Activity::query()->sole())
        ->log_name->toBe('content')
        ->event->toBe('content.post_created')
        ->description->toBe('content.post_created');
});

it('resolves the subject and causer through the morph map', function (): void {
    $user = createUser();
    $post = createPost($user);

    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated, $post, $user);

    $activity = Activity::query()->sole();

    expect($activity)
        ->subject_type->toBe('post')
        ->causer_type->toBe('user')
        ->and($activity->subject)->toBeInstanceOf(Post::class)->is($post)->toBeTrue()
        ->and($activity->causer)->toBeInstanceOf(User::class)->is($user)->toBeTrue();
});

it('stores an anonymous causer when none is given even with an authenticated user', function (): void {
    $this->actingAs(createUser());

    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated);

    expect(Activity::query()->sole())
        ->causer_type->toBeNull()
        ->causer_id->toBeNull();
});

it('keeps the given properties', function (): void {
    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::CommentDeleted, properties: ['permanently' => true]);

    expect(Activity::query()->sole()->properties?->all())->toBe(['permanently' => true]);
});

it('adds the ip and user agent of a routed request', function (): void {
    $post = createPost();
    $probeUrl = registerActivityLoggerProbeRoute($post);

    $this->withHeader('User-Agent', chromeWindowsUserAgent())->get($probeUrl)->assertOk();

    expect(Activity::query()->sole()->properties?->all())->toBe([
        'ip' => '127.0.0.1',
        'user_agent' => chromeWindowsUserAgent(),
    ]);
});

it('truncates a user agent longer than 512 characters', function (): void {
    $post = createPost();
    $probeUrl = registerActivityLoggerProbeRoute($post);

    $this->withHeader('User-Agent', str_repeat('a', 600))->get($probeUrl)->assertOk();

    expect(Activity::query()->sole()->properties?->get('user_agent'))->toBe(str_repeat('a', 512));
});

it('does not add request context when no route is being handled', function (): void {
    resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated);

    expect(Activity::query()->sole()->properties?->all())
        ->toBeEmpty();
});

it('rejects a forbidden property key without storing anything', function (string $propertyKey): void {
    expect(fn () => resolve(ActivityLogger::class)->record(ActivityLogContentEvent::PostCreated, properties: [$propertyKey => 'secret']))
        ->toThrow(InvalidArgumentException::class)
        ->and(Activity::query()
            ->count())
        ->toBe(0);
})->with([
    'password',
    'password_confirmation',
    'current_password',
    'token',
    'token_hash',
    'code',
    'otp_code',
    'recovery_code',
    'recovery_codes',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'content',
    'email',
    'uppercase key' => 'Password',
]);
