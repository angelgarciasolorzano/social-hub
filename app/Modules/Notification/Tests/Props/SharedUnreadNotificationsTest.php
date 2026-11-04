<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a notification row for a shared unread-count integration test.
 */
function createSharedUnreadNotification(User $user, bool $isRead = false): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'notification.shared-test',
        'data' => [],
        'read_at' => $isRead ? now() : null,
    ]);
}

it('shares zero with guests without querying user or notification records', function (): void {
    $userWithNotifications = createUser();
    createSharedUnreadNotification($userWithNotifications);

    $databaseConnection = DB::connection();
    $databaseConnection->flushQueryLog();
    $databaseConnection->enableQueryLog();

    $this->get('/')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('auth.user', null)
            ->missing('unreadNotificationsCount')
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 0)
            )
        );

    $userAndNotificationQueries = array_filter(
        $databaseConnection->getQueryLog(),
        fn (array $query): bool => str_contains(strtolower($query['query']), 'users')
            || str_contains(strtolower($query['query']), 'notifications'),
    );
    $databaseConnection->disableQueryLog();

    expect($userAndNotificationQueries)->toBeEmpty();
});

it('shares the deferred count and existing props with a user without notifications', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('auth.user.id', $user->id)
            ->has('announcements')
            ->where('locale', $user->preferred_locale->value)
            ->where('search_results', null)
            ->where('translations.dates.never', translatedTextIn($user->preferred_locale, 'dates.never'))
            ->where(
                'translations.notification/center.page.title',
                translatedTextIn($user->preferred_locale, 'notification/center.page.title'),
            )
            ->missing('unreadNotificationsCount')
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 0)
            )
        );
});

it('shares only the authenticated user unread notification count', function (): void {
    $user = createUser();
    createSharedUnreadNotification($user);
    createSharedUnreadNotification($user);
    createSharedUnreadNotification($user, isRead: true);

    $otherUser = createUser();
    createSharedUnreadNotification($otherUser);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->missing('unreadNotificationsCount')
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('unreadNotificationsCount', 2)
            )
        );
});
