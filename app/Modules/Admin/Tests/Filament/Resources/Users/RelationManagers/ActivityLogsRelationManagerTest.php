<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\ActivityLogsRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Models\User;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return Testable<Component>
 */
function activityLogsRelationManagerFor(User $user): Testable
{
    return Livewire::test(ActivityLogsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('loads the activity relation lazily and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminActivityReader);

    $table = FilamentTableRecords::table(activityLogsRelationManagerFor(createUser()))->getTable();

    expect(ActivityLogsRelationManager::isLazy())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeFalse()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
});

it('lists the activity the user performed and the activity performed on them, newest first', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminActivityReader);
    $user = createUser();
    $otherUser = createUser();

    $performedActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::PostCreated)->causedBy($user)->createOne();
    $receivedActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->causedBy($otherUser)->performedOn($user)->createOne();
    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleRevoked)->causedBy($otherUser)->performedOn($otherUser)->createOne();

    $testable = activityLogsRelationManagerFor($user)->assertOk();

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), ActivityLog::class))
        ->toBe([
            FilamentTableRecords::recordKey($receivedActivityLog),
            FilamentTableRecords::recordKey($performedActivityLog),
        ]);
});

it('hides the tab from an administrator without admin.activity.viewAny', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);
    $user = createUser();

    expect(ActivityLogsRelationManager::canViewForRecord($user, ViewUser::class))->toBeFalse();

    Livewire::test(ActivityLogsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ])->assertForbidden();
});

it('hides the tab from an activity reader who cannot view users', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);
    $user = createUser();

    expect(ActivityLogsRelationManager::canViewForRecord($user, ViewUser::class))->toBeFalse();
});
