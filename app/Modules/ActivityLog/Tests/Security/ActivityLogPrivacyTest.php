<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

const PRIVACY_SENTINEL_POST = 'centinela-texto-de-publicacion';
const PRIVACY_SENTINEL_COMMENT = 'centinela-texto-de-comentario';
const PRIVACY_SENTINEL_EDITED_COMMENT = 'centinela-comentario-editado';
const PRIVACY_SENTINEL_NEW_PASSWORD = 'centinela-contrasena-nueva';
const PRIVACY_SENTINEL_WRONG_PASSWORD = 'centinela-contrasena-equivocada';
const PRIVACY_SENTINEL_UNKNOWN_EMAIL = 'centinela-correo@example.com';
const PRIVACY_SENTINEL_BIOGRAPHY = 'centinela-biografia-privada';
const PRIVACY_SENTINEL_PHONE = '+505 0000 7777';

/**
 * Contents of the application source files that may record an event: everything
 * except the event enums themselves and the test folders. Read once per run.
 */
function activityLogEventCallerSources(): string
{
    static $callerSources = null;

    if (is_string($callerSources)) {
        return $callerSources;
    }

    $callerFiles = array_filter(
        File::allFiles(app_path()),
        fn (SplFileInfo $splFileInfo): bool => $splFileInfo->getExtension() === 'php'
            && $splFileInfo->getRelativePath() !== 'Modules/ActivityLog/Enums/Events'
            && ! str_contains($splFileInfo->getRelativePath(), 'Tests'),
    );

    $callerSources = implode('', array_map(fn (SplFileInfo $splFileInfo): string => $splFileInfo->getContents(), $callerFiles));

    return $callerSources;
}

arch('only the ActivityLog module talks to the activity log package')
    ->expect('Spatie\Activitylog')
    ->toOnlyBeUsedIn('App\Modules\ActivityLog');

it('never stores content, passwords or attempted emails across the recorded flows', function (): void {
    $user = createUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => PRIVACY_SENTINEL_WRONG_PASSWORD]);
    $this->post(route('login.store'), ['email' => PRIVACY_SENTINEL_UNKNOWN_EMAIL, 'password' => PRIVACY_SENTINEL_WRONG_PASSWORD]);

    $this->actingAs($user)->post(route('post.store'), ['content' => PRIVACY_SENTINEL_POST]);
    $post = Post::query()->latest('id')->firstOrFail();

    $this->post(route('comments.store'), [
        'content' => PRIVACY_SENTINEL_COMMENT,
        'commentable_type' => $post->getMorphClass(),
        'commentable_id' => $post->id,
    ]);
    $comment = $post->comments()->latest('id')->firstOrFail();
    $this->patch(route('comments.update', $comment), ['content' => PRIVACY_SENTINEL_EDITED_COMMENT]);

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'phone' => PRIVACY_SENTINEL_PHONE,
        'preferredLocale' => $user->preferred_locale->value,
        'biography' => PRIVACY_SENTINEL_BIOGRAPHY,
    ]);

    $this->put(route('setting.password.update'), [
        'current_password' => 'password',
        'password' => PRIVACY_SENTINEL_NEW_PASSWORD,
        'password_confirmation' => PRIVACY_SENTINEL_NEW_PASSWORD,
    ]);

    $storedActivities = DB::table('activity_log')->get()->toJson();
    $leakedValues = array_filter(
        [
            PRIVACY_SENTINEL_POST,
            PRIVACY_SENTINEL_COMMENT,
            PRIVACY_SENTINEL_EDITED_COMMENT,
            PRIVACY_SENTINEL_NEW_PASSWORD,
            PRIVACY_SENTINEL_WRONG_PASSWORD,
            PRIVACY_SENTINEL_UNKNOWN_EMAIL,
            PRIVACY_SENTINEL_BIOGRAPHY,
            PRIVACY_SENTINEL_PHONE,
            $user->email,
        ],
        fn (string $sensitiveValue): bool => str_contains($storedActivities, $sensitiveValue),
    );

    expect(DB::table('activity_log')->pluck('event')->all())->toContain(
        'auth.login_failed',
        'content.post_created',
        'content.comment_created',
        'content.comment_updated',
        'account.updated',
        'auth.password_updated',
    )
        ->and($leakedValues)->toBeEmpty();
});

it('records every cataloged event from some listener, command or service', function (string $enumCaseReference): void {
    expect(str_contains(activityLogEventCallerSources(), $enumCaseReference))->toBeTrue("No listener, command or service records {$enumCaseReference}.");
})->with(function (): array {
    $cases = [];

    foreach (ActivityLogEvent::ENUMS as $enumClass) {
        foreach ($enumClass::cases() as $case) {
            $enumCaseReference = class_basename($enumClass).'::'.$case->name;
            $cases[$enumCaseReference] = [$enumCaseReference];
        }
    }

    return $cases;
});
