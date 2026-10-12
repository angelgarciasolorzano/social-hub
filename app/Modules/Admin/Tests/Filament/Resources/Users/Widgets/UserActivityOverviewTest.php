<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\Widgets\UserActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use Livewire\Livewire;

beforeEach(function (): void {
    AdminFilamentTestContext::configure($this);
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
        ->assertSeeText('0');
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
