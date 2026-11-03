<?php

declare(strict_types=1);

use App\Modules\Notification\Props\NotificationProps;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function createNotificationPropsTestNotification(User $user, bool $isRead = false): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'notification.props.test',
        'data' => [],
        'read_at' => $isRead ? now() : null,
    ]);
}

beforeEach(function (): void {
    Route::middleware('web')->get('/__test/notification-props', fn (Request $request): Response => Inertia::render('home/Home', [
        new NotificationProps($request),
    ]));
});

it('defers the count and includes only unread notifications owned by the authenticated user', function (): void {
    $user = createUser();
    createNotificationPropsTestNotification($user);
    createNotificationPropsTestNotification($user, isRead: true);

    $otherUser = createUser();
    createNotificationPropsTestNotification($otherUser);

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $testResponse = actingAs($user)->get('/__test/notification-props');
    $initialQueries = $connection->getQueryLog();
    $connection->disableQueryLog();

    $notificationQueries = collect($initialQueries)
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'notifications'));

    expect($notificationQueries)->toBeEmpty();

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->missing('unreadNotificationsCount')
        ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
            ->where('unreadNotificationsCount', 1)
        )
    );
});

it('returns zero when the authenticated user has no unread notifications', function (): void {
    $user = createUser();
    createNotificationPropsTestNotification($user, isRead: true);

    actingAs($user)
        ->get('/__test/notification-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->missing('unreadNotificationsCount')
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 0)
            )
        );
});

it('reduces the deferred count after a notification is marked as read', function (): void {
    $user = createUser();
    $databaseNotification = createNotificationPropsTestNotification($user);

    actingAs($user)
        ->get('/__test/notification-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 1)
            )
        );

    $databaseNotification->markAsRead();

    actingAs($user)
        ->get('/__test/notification-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 0)
            )
        );
});
