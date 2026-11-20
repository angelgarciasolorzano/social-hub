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
use App\User\Models\User;
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

function createUserNamed(string $name): User
{
    $user = createUser();
    $user->forceFill(['name' => $name])->save();

    return $user;
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

    expect(array_keys($table->getColumns()))->toBe(['other_user', 'status', 'sent_by', 'since'])
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
