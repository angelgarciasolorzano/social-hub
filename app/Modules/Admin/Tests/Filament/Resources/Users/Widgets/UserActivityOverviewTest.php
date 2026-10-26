<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

function createUserActivityOverviewStatusChange(User $user, string $createdAt, string $reason = 'Account status changed'): AccountStatusChange
{
    return AccountStatusChangeFactory::new()->createOne([
        'created_at' => $createdAt,
        'reason' => $reason,
        'user_id' => $user->getKey(),
    ]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('renders the selected users post comment and active trusted device counts', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    $firstPost = createPost($targetUser);
    $secondPost = createPost($targetUser);

    $firstComment = createComment($targetUser, [
        'commentable_id' => $firstPost->getKey(),
        'commentable_type' => $firstPost->getMorphClass(),
    ]);

    createComment($targetUser, [
        'commentable_id' => $firstComment->getKey(),
        'commentable_type' => $firstComment->getMorphClass(),
    ]);

    createComment($targetUser, [
        'commentable_id' => $secondPost->getKey(),
        'commentable_type' => $secondPost->getMorphClass(),
    ]);

    $tokenHashSentinel = 'activity-token-hash-sentinel';
    $userAgentSentinel = 'activity-user-agent-sentinel';
    $ipSentinel = 'activity-ip-sentinel';

    createTrustedDevice($targetUser, [
        'token_hash' => $tokenHashSentinel,
        'user_agent' => $userAgentSentinel,
        'ip' => $ipSentinel,
        'expires_at' => now()->addDay(),
    ]);

    createTrustedDevice($targetUser, ['expires_at' => now()->subDay()]);
    createTrustedDevice($targetUser, ['expires_at' => now()->addDay()])->delete();

    $this->actingAs($authenticatedUser, 'admin');

    $widgetTestable = Livewire::test(UserActivityOverview::class, ['record' => $targetUser])
        ->assertSeeText('Posts')
        ->assertSeeText('2')
        ->assertSeeText('Comments')
        ->assertSeeText('3')
        ->assertSeeText('Active trusted devices')
        ->assertSeeText('1');

    expect($widgetTestable->html())
        ->not->toContain($tokenHashSentinel)
        ->not->toContain($userAgentSentinel)
        ->not->toContain($ipSentinel)
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
});

it('shows zero counts when the selected user has no activity', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(UserActivityOverview::class, ['record' => $targetUser])
        ->assertSeeText('Posts')
        ->assertSeeText('Comments')
        ->assertSeeText('Active trusted devices')
        ->assertSeeText('Status changes (30 days)')
        ->assertSeeText('0');
});

it('counts account status changes within the last 30 calendar days', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00'));

    createUserActivityOverviewStatusChange($targetUser, '2026-10-01 00:00:00');
    createUserActivityOverviewStatusChange($targetUser, '2026-10-30 23:59:59');
    createUserActivityOverviewStatusChange($targetUser, '2026-09-30 23:59:59');

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(UserActivityOverview::class, ['record' => $targetUser])
        ->assertSeeTextInOrder(['Status changes (30 days)', '2']);
});

it('counts only the selected users status changes without exposing reasons', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();
    $otherUser = createUser();
    $privateReason = 'private-status-change-reason-sentinel';

    createUserActivityOverviewStatusChange($targetUser, now()->toDateTimeString());
    createUserActivityOverviewStatusChange($otherUser, now()->toDateTimeString(), $privateReason);
    createUserActivityOverviewStatusChange($otherUser, now()->toDateTimeString());

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(UserActivityOverview::class, ['record' => $targetUser])
        ->assertSeeTextInOrder(['Status changes (30 days)', '1'])
        ->assertDontSee($privateReason);
});

it('forbids access when the admin lacks users view permission', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole(AdminRole::Admin->value);

    $this->actingAs($authenticatedUser, 'admin');

    $targetUser = createUser();

    Livewire::test(ViewUser::class, ['record' => $targetUser->getKey()])
        ->assertForbidden();

    Livewire::test(UserActivityOverview::class, ['record' => $targetUser])
        ->assertForbidden();
});
