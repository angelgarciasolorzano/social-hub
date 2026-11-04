<?php

declare(strict_types=1);

use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Create a notification with stable timestamps for notification center endpoint tests.
 */
function createNotificationControllerTestNotification(
    User $user,
    CarbonImmutable $createdAt,
    ?string $id = null,
    ?CarbonImmutable $readAt = null,
): DatabaseNotification {
    return $user->notifications()->create([
        'id' => $id ?? Str::uuid()->toString(),
        'type' => 'comment_on_post',
        'data' => [
            'summary' => 'A user commented on your post.',
            'actors' => [],
            'target' => [
                'postId' => 123,
                'commentId' => 456,
            ],
            'private' => 'must not be serialized',
        ],
        'read_at' => $readAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/**
 * Read notification ids from a JSON resource response after validating the payload shape.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<string>
 */
function notificationControllerTestIds(TestResponse $testResponse, string $path = 'data'): array
{
    $rows = $testResponse->json($path);

    throw_unless(\is_array($rows), UnexpectedValueException::class, 'The notification response did not contain an array of rows.');

    $ids = [];

    foreach ($rows as $row) {
        throw_if(! \is_array($row) || ! \is_string($row['id'] ?? null), UnexpectedValueException::class, 'The notification response contained a row without a string id.');

        $ids[] = $row['id'];
    }

    return $ids;
}

/**
 * Extract the rendered notification history and scroll cursor from an Inertia response.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return array{component: string, notificationIds: list<string>, nextCursor: string|null}
 */
function notificationControllerHistoryPage(TestResponse $testResponse): array
{
    $page = $testResponse->viewData('page');

    throw_unless(\is_array($page), UnexpectedValueException::class, 'The response did not contain an Inertia page.');

    $component = $page['component'] ?? null;
    $props = $page['props'] ?? null;
    $scrollProps = $page['scrollProps'] ?? null;

    throw_if(! \is_string($component) || ! \is_array($props) || ! \is_array($scrollProps), UnexpectedValueException::class, 'The Inertia page did not contain notification history metadata.');

    $notifications = $props['notifications'] ?? null;
    $notificationScrollProps = $scrollProps['notifications'] ?? null;

    throw_if(! \is_array($notifications) || ! \is_array($notificationScrollProps), UnexpectedValueException::class, 'The Inertia page did not contain the notifications prop.');

    $rows = $notifications['data'] ?? null;
    $nextCursor = $notificationScrollProps['nextPage'] ?? null;

    throw_if(! \is_array($rows) || (! \is_string($nextCursor) && $nextCursor !== null), UnexpectedValueException::class, 'The notification history page contained invalid pagination data.');

    $notificationIds = [];

    foreach ($rows as $row) {
        throw_if(! \is_array($row) || ! \is_string($row['id'] ?? null), UnexpectedValueException::class, 'The notification history contained a row without a string id.');

        $notificationIds[] = $row['id'];
    }

    return [
        'component' => $component,
        'notificationIds' => $notificationIds,
        'nextCursor' => $nextCursor,
    ];
}

it('requires authentication for notification history, recent notifications, and read actions', function (): void {
    $notificationId = Str::uuid()->toString();

    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->get(route('notifications.recent'))->assertRedirect(route('login'));
    $this->post(route('notifications.read', ['notificationId' => $notificationId]))->assertRedirect(route('login'));
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
});

it('returns only the five most recent notifications owned by the authenticated user', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $baseTime = CarbonImmutable::parse('2026-11-01T10:00:00+00:00');
    $notificationIds = [];

    foreach (range(0, 6) as $offset) {
        $notification = createNotificationControllerTestNotification(
            $user,
            $baseTime->addSeconds($offset),
        );

        $notificationIds[] = $notification->id;
    }

    $databaseNotification = createNotificationControllerTestNotification(
        $otherUser,
        $baseTime->addDay(),
    );

    $testResponse = $this->actingAs($user)->getJson(route('notifications.recent'));
    $expectedIds = array_slice(array_reverse($notificationIds), 0, 5);

    $testResponse->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.id', $expectedIds[0])
        ->assertJsonMissingPath('data.0.private');

    expect(notificationControllerTestIds($testResponse))
        ->toBe($expectedIds)
        ->not->toContain($databaseNotification->id);
});

it('serves stable cursor-paginated history newest first using the creation time and id', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $createdAt = CarbonImmutable::parse('2026-11-01T10:00:00+00:00');
    $notificationIds = [];

    foreach (range(1, 22) as $number) {
        $notification = createNotificationControllerTestNotification(
            $user,
            $createdAt,
        );

        $notificationIds[] = $notification->id;
    }

    $databaseNotification = createNotificationControllerTestNotification(
        $otherUser,
        $createdAt->addSecond(),
    );
    sort($notificationIds, SORT_STRING);
    $expectedIds = array_reverse($notificationIds);

    $firstPage = notificationControllerHistoryPage($this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk());

    $nextCursor = $firstPage['nextCursor'];

    throw_unless(\is_string($nextCursor), UnexpectedValueException::class, 'The first notification history page did not include a next cursor.');

    $secondPage = notificationControllerHistoryPage($this->get(route('notifications.index', ['cursor' => $nextCursor]))
        ->assertOk());

    expect($firstPage['component'])->toBe('notification/Notifications')
        ->and($firstPage['notificationIds'])
        ->toBe(array_slice($expectedIds, 0, 20))
        ->and($secondPage['notificationIds'])
        ->toBe(array_slice($expectedIds, 20))
        ->and($expectedIds)->not->toContain($databaseNotification->id);
});

it('shares the notification center translations on the history page', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk();
    $page = $testResponse->viewData('page');
    throw_unless(\is_array($page), UnexpectedValueException::class, 'The response did not contain an Inertia page.');

    $props = $page['props'] ?? null;
    throw_unless(\is_array($props), UnexpectedValueException::class, 'The Inertia page did not contain props.');

    $translations = $props['translations'] ?? null;
    throw_unless(\is_array($translations), UnexpectedValueException::class, 'The Inertia page did not contain translations.');

    $translationGroup = $translations['notification/center'] ?? null;
    throw_unless(\is_array($translationGroup), UnexpectedValueException::class, 'The notification translation group was not shared.');

    $history = $translationGroup['history'] ?? null;
    throw_unless(\is_array($history), UnexpectedValueException::class, 'The notification history translations were not shared.');

    expect($history['empty_title'] ?? null)
        ->toBe(__('notification/center.history.empty_title'));
});

it('marks only the authenticated user notification as read and returns not found for other users notifications', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $createdAt = CarbonImmutable::parse('2026-11-01T10:00:00+00:00');
    $databaseNotification = createNotificationControllerTestNotification($user, $createdAt);
    $otherUserNotification = createNotificationControllerTestNotification($otherUser, $createdAt);

    $this->actingAs($user)
        ->post(route('notifications.read', ['notificationId' => $databaseNotification->id]))
        ->assertNoContent();

    $this->post(route('notifications.read', ['notificationId' => $otherUserNotification->id]))
        ->assertNotFound();

    $this->post(route('notifications.read', ['notificationId' => Str::uuid()->toString()]))
        ->assertNotFound();

    expect($databaseNotification->refresh()->read_at)->not->toBeNull()
        ->and($otherUserNotification->refresh()->read_at)->toBeNull();
});

it('marks only the authenticated users unread notifications as read', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $createdAt = CarbonImmutable::parse('2026-11-01T10:00:00+00:00');
    $databaseNotification = createNotificationControllerTestNotification($user, $createdAt);
    $secondUnread = createNotificationControllerTestNotification($user, $createdAt->addSecond());
    $existingReadAt = $createdAt->addMinute();
    $alreadyRead = createNotificationControllerTestNotification($user, $createdAt->addMinutes(2), readAt: $existingReadAt);
    $otherUserUnread = createNotificationControllerTestNotification($otherUser, $createdAt);

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertNoContent();

    expect($databaseNotification->refresh()->read_at)->not->toBeNull()
        ->and($secondUnread->refresh()->read_at)->not->toBeNull()
        ->and($alreadyRead->refresh()->read_at?->toIso8601String())->toBe($existingReadAt->toIso8601String())
        ->and($otherUserUnread->refresh()->read_at)->toBeNull();
});

it('throttles notification read actions', function (): void {
    $user = createUser();
    $databaseNotification = createNotificationControllerTestNotification(
        $user,
        CarbonImmutable::parse('2026-11-01T10:00:00+00:00'),
    );
    $response = $this->actingAs($user)->post(route('notifications.read-all'));

    foreach (range(1, 29) as $attempt) {
        $response = $this->post(route('notifications.read-all'));
    }

    $response->assertNoContent();
    $this->post(route('notifications.read-all'))->assertTooManyRequests();

    $response = $this->post(route('notifications.read', [
        'notificationId' => $databaseNotification->id,
    ]));

    foreach (range(1, 29) as $attempt) {
        $response = $this->post(route('notifications.read', [
            'notificationId' => $databaseNotification->id,
        ]));
    }

    $response->assertNoContent();
    $this->post(route('notifications.read', [
        'notificationId' => $databaseNotification->id,
    ]))->assertTooManyRequests();
});
