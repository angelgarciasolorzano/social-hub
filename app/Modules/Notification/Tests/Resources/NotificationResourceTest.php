<?php

declare(strict_types=1);

use App\Modules\Notification\Resources\NotificationResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('serializes only the public notification contract', function (): void {
    $user = createUser();
    $actor = createUser();
    $createdAt = CarbonImmutable::parse('2026-11-01T10:00:00+00:00');
    $readAt = CarbonImmutable::parse('2026-11-01T10:05:00+00:00');
    $databaseNotification = $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'comment_on_post',
        'data' => [
            'summary' => 'A user commented on your post.',
            'actors' => [[
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'content' => 'private actor content',
            ]],
            'target' => [
                'postId' => 123,
                'commentId' => 456,
                'content' => 'private target content',
            ],
            'email' => $user->email,
            'content' => 'private notification content',
        ],
        'read_at' => $readAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $payload = new NotificationResource($databaseNotification)->toArray(request());

    expect(array_keys($payload))->toBe([
        'id',
        'type',
        'summary',
        'actors',
        'target',
        'readAt',
        'createdAt',
    ])
        ->and($payload)->toBe([
            'id' => $databaseNotification->id,
            'type' => 'comment_on_post',
            'summary' => 'A user commented on your post.',
            'actors' => [[
                'id' => $actor->id,
                'name' => $actor->name,
            ]],
            'target' => [
                'postId' => 123,
                'commentId' => 456,
            ],
            'readAt' => '2026-11-01T10:05:00+00:00',
            'createdAt' => '2026-11-01T10:00:00+00:00',
        ]);
});

it('serializes a saved target after its post and comment have been deleted without querying relations', function (): void {
    $user = createUser();
    $actor = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $actor);
    $databaseNotification = $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'comment_on_post',
        'data' => [
            'summary' => 'Saved summary from the notification.',
            'actors' => [['id' => $actor->id, 'name' => $actor->name]],
            'target' => ['postId' => $post->id, 'commentId' => $comment->id],
        ],
        'read_at' => null,
    ]);

    $comment->forceDelete();
    $post->delete();

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $payload = new NotificationResource($databaseNotification)->toArray(request());
    $queries = $connection->getQueryLog();
    $connection->disableQueryLog();

    expect($payload['summary'])->toBe('Saved summary from the notification.')
        ->and($payload['target'])->toBe([
            'postId' => $post->id,
            'commentId' => $comment->id,
        ])
        ->and($payload['readAt'])->toBeNull()
        ->and($queries)->toBeEmpty();
});
