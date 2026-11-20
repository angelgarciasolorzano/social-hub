<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\FriendshipsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Admin\Tests\Support\QueryLog;
use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return Testable<Component>
 */
function friendshipsRelationManagerFor(User $user): Testable
{
    return Livewire::test(FriendshipsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

/**
 * @param  Testable<Component>  $testable
 */
function friendshipsStatusColumn(Testable $testable): TextColumn
{
    $statusColumn = FilamentTableRecords::table($testable)->getTable()->getColumn('status');

    throw_unless($statusColumn instanceof TextColumn, LogicException::class, 'The friendships table must expose its status column.');

    return $statusColumn;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('registers the friendships relation on the user resource', function (): void {
    expect(UserResource::getRelations())->toContain(FriendshipsRelationManager::class);
});

it('loads friendships lazily and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $table = FilamentTableRecords::table(friendshipsRelationManagerFor(createUser()))->getTable();

    expect(FriendshipsRelationManager::isLazy())->toBeTrue()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
});

it('lists the friends and the requests of both directions with the status seen from the user', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friend = createUserNamed('Friend Sentinel');
    $receiver = createUserNamed('Receiver Sentinel');
    $requester = createUserNamed('Requester Sentinel');
    $friendship = createFriendship($friend, $user);
    $sent = createFriendRequest($user, $receiver);
    $received = createFriendRequest($requester, $user);
    $foreign = createFriendship(createUserNamed('Stranger Sentinel'));

    $testable = friendshipsRelationManagerFor($user)
        ->assertOk()
        ->assertSeeText('Friend Sentinel')
        ->assertSeeText('Receiver Sentinel')
        ->assertSeeText('Requester Sentinel')
        ->assertDontSeeText('Stranger Sentinel');

    $textColumn = friendshipsStatusColumn($testable);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toEqualCanonicalizing([(string) $friendship->id, (string) $sent->id, (string) $received->id])
        ->not->toContain((string) $foreign->id)
        ->and($textColumn->record($friendship)->getState())->toBe('accepted')
        ->and($textColumn->record($sent)->getState())->toBe('pending_sent')
        ->and($textColumn->record($received)->getState())->toBe('pending_received')
        ->and($textColumn->getColor('pending_received'))->toBe('warning');
});

it('filters the friendships by status seen from the user', function (string $status, string $expected): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friendships = [
        'accepted' => createFriendship($user),
        'pending_sent' => createFriendRequest($user),
        'pending_received' => createFriendRequest(createUser(), $user),
    ];

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('filterTable', ['status', $status]);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toBe([(string) $friendships[$expected]->id]);
})->with([
    'accepted' => ['accepted', 'accepted'],
    'sent' => ['pending_sent', 'pending_sent'],
    'received' => ['pending_received', 'pending_received'],
]);

it('filters the requests by who sent them', function (string $direction): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $sent = [createFriendRequest($user), createFriendship($user)];
    $received = [createFriendRequest(createUser(), $user), createFriendship(createUser(), $user)];

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('filterTable', ['direction', $direction]);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))->toEqualCanonicalizing(array_map(
        static fn (Friendship $friendship): string => (string) $friendship->id,
        $direction === 'sent' ? $sent : $received,
    ));
})->with(['sent', 'received']);

it('searches by the start of the other user name, never the owner name', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUserNamed('Alma Owner');
    $friendship = createFriendship($user, createUserNamed('Alba Sent'));
    $albert = createFriendRequest(createUserNamed('Albert Received'), $user);
    createFriendship($user, createUserNamed('Bruno Alba'));

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('searchTable', ['Alb']);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toEqualCanonicalizing([(string) $friendship->id, (string) $albert->id]);

    $testable->__call('searchTable', ['Alma']);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toBeEmpty();
});

it('searches a percent sign literally', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friendship = createFriendship($user, createUserNamed('100% Real'));
    createFriendship($user, createUserNamed('1000 Fake'));

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('searchTable', ['100%']);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))->toBe([(string) $friendship->id]);
});

it('summarizes the friends and the requests of both directions', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    createFriendship($user);
    createFriendship(createUser(), $user);
    createFriendRequest(createUser(), $user);
    createFriendRequest($user);
    createFriendRequest($user);
    createFriendship();

    $table = FilamentTableRecords::table(friendshipsRelationManagerFor($user))->getTable();

    expect($table->getDescription())->toBe('Friends: 2 · Requests received: 1 · Requests sent: 2');
});

it('shows no summary and an empty state without friendships', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $testable = friendshipsRelationManagerFor(createUser())
        ->assertSeeText('No friends or requests');

    expect(FilamentTableRecords::table($testable)->getTable()->getDescription())->toBeNull();
});

it('shows the picture, email and account of the other user', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friend = createSuspendedUser();
    $friendship = createFriendship($user, $friend);

    $table = FilamentTableRecords::table(friendshipsRelationManagerFor($user)->assertSeeText($friend->email))->getTable();
    $accountColumn = $table->getColumn('account');
    $pictureColumn = $table->getColumn('profile_picture');

    throw_unless($accountColumn instanceof TextColumn && $pictureColumn instanceof ImageColumn, LogicException::class, 'The friendships table must expose the account and picture columns.');

    expect($accountColumn->record($friendship)->getState())->toBe(AccountStatus::Suspended)
        ->and($pictureColumn->record($friendship)->getState())->toBe(url(User::DEFAULT_PROFILE_PICTURE_PATH));
});

it('filters by the account status of the other user, never the owner', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createSuspendedUser();
    $friendship = createFriendship($user, createSuspendedUser());
    $suspendedRequester = createFriendRequest(createSuspendedUser(), $user);
    createFriendship($user);

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('filterTable', ['account', AccountStatus::Suspended->value]);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toEqualCanonicalizing([(string) $friendship->id, (string) $suspendedRequester->id]);
});

it('filters by the date the friendship was accepted, or the request sent while pending', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friendship = createFriendship($user);
    $friendship->forceFill(['created_at' => '2026-01-01 10:00:00', 'accepted_at' => '2026-03-10 10:00:00'])->save();
    $sentInRange = createFriendRequest($user);
    $sentInRange->forceFill(['created_at' => '2026-03-20 23:00:00'])->save();
    $acceptedBefore = createFriendship($user);
    $acceptedBefore->forceFill(['created_at' => '2026-03-05 10:00:00', 'accepted_at' => '2026-03-09 10:00:00'])->save();
    $sentAfter = createFriendRequest(createUser(), $user);
    $sentAfter->forceFill(['created_at' => '2026-03-21 00:00:00'])->save();

    $testable = friendshipsRelationManagerFor($user);
    $testable->__call('filterTable', ['date', ['date_from' => '2026-03-10', 'date_until' => '2026-03-20']]);

    expect(FilamentTableRecords::shownKeys($testable, Friendship::class))
        ->toEqualCanonicalizing([(string) $friendship->id, (string) $sentInRange->id]);
});

it('sorts by the date of the friendship, newest first', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $oldFriendship = createFriendship($user);
    $oldFriendship->forceFill(['created_at' => now()->subDays(10), 'accepted_at' => now()->subDays(5)])->save();
    $friendship = createFriendRequest($user);
    $friendship->forceFill(['created_at' => now()->subDay()])->save();
    $oldRequest = createFriendRequest(createUser(), $user);
    $oldRequest->forceFill(['created_at' => now()->subDays(8)])->save();

    expect(FilamentTableRecords::shownKeys(friendshipsRelationManagerFor($user), Friendship::class))
        ->toBe([(string) $friendship->id, (string) $oldFriendship->id, (string) $oldRequest->id]);
});

it('links the other user to their detail page', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    $friend = createUser();
    $friendship = createFriendship($user, $friend);

    $column = FilamentTableRecords::table(friendshipsRelationManagerFor($user))->getTable()->getColumn('other_user');

    throw_unless($column instanceof TextColumn, LogicException::class, 'The friendships table must expose the other user column.');

    expect($column->record($friendship)->getUrl())->toBe(UserResource::getUrl('view', ['record' => $friend]));
});

it('defines the friendship columns without any action', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $user = createUser();
    createFriendship($user);

    $table = FilamentTableRecords::table(friendshipsRelationManagerFor($user)->assertOk())->getTable();

    expect(array_keys($table->getColumns()))->toBe(['profile_picture', 'other_user', 'account', 'status', 'sent_by', 'since', 'created_at', 'accepted_at', 'last_login'])
        ->and($table->getRecordActions())->toBeEmpty()
        ->and($table->getHeaderActions())->toBeEmpty()
        ->and($table->getToolbarActions())->toBeEmpty();
});

it('hides the tab and forbids mounting it without admin.users.friendships', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    grantAdminPermissionThroughTestRole($authenticatedUser, AdminPermission::AdminUsersViewAny);
    grantAdminPermissionThroughTestRole($authenticatedUser, AdminPermission::AdminUsersView);

    $this->actingAs($authenticatedUser, 'admin');

    $targetUser = createUser();

    expect(FriendshipsRelationManager::canViewForRecord($targetUser, ViewUser::class))->toBeFalse();

    friendshipsRelationManagerFor($targetUser)->assertForbidden();
});

it('lists 3 or 15 friendships with the same number of queries', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $fewUser = createUser();
    $manyUser = createUser();

    foreach (range(1, 3) as $sequence) {
        $sequence % 2 === 0 ? createFriendship($fewUser) : createFriendRequest(createUser(), $fewUser);
    }

    foreach (range(1, 15) as $sequence) {
        $sequence % 2 === 0 ? createFriendship($manyUser) : createFriendRequest(createUser(), $manyUser);
    }

    $renderTab = static fn (User $user): Closure => static fn (): Testable => friendshipsRelationManagerFor($user)
        ->set('tableRecordsPerPage', 25)
        ->assertOk();

    QueryLog::count($renderTab($fewUser));

    expect(QueryLog::count($renderTab($manyUser)))->toBe(QueryLog::count($renderTab($fewUser)));
});
