<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    AdminFilamentTestContext::configure($this);
});

it('allows Posts readers to view a post and its author', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost($user, ['content' => 'Post detail content sentinel']);
    $createdAt = $post->created_at;
    $updatedAt = $post->updated_at;

    throw_unless($createdAt instanceof CarbonImmutable, LogicException::class, 'The post creation timestamp must exist.');
    throw_unless($updatedAt instanceof CarbonImmutable, LogicException::class, 'The post update timestamp must exist.');

    $this->actingAs($authenticatedUser, 'admin');

    expect($authenticatedUser->can(AdminPermission::AdminUsersViewAny->value))
        ->toBeFalse()
        ->and($authenticatedUser->can(AdminPermission::AdminUsersView->value))
        ->toBeFalse()
        ->and(array_keys(PostResource::getPages()))
        ->toBe(['index', 'view']);

    $this->get(PostResource::getUrl('view', ['record' => $post]))
        ->assertOk();

    Livewire::test(ListPosts::class)
        ->assertSee(PostResource::getUrl('view', ['record' => $post]), false);

    $testable = Livewire::test(ViewPost::class, ['record' => $post->getKey()])
        ->assertOk()
        ->assertSeeText('Post detail content sentinel')
        ->assertSeeText($user->name)
        ->assertSeeText('Created at')
        ->assertSeeText('Updated at')
        ->assertSeeText($createdAt->format('M j, Y'))
        ->assertSeeText($updatedAt->format('M j, Y'));

    $component = $testable->instance();

    throw_unless($component instanceof ViewPost, LogicException::class, 'The detail test must resolve the Filament ViewPost page.');

    expect($component->getCachedHeaderActions())->toBeEmpty();
});

it('denies the direct post detail route to users with only Users read access', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(PostResource::getUrl('view', ['record' => $post]))
        ->assertForbidden();
});

it('denies post detail when the post has no existing owner', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $post = createPost();
    $post->forceFill(['user_id' => PHP_INT_MAX])->save();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ViewPost::class, ['record' => $post->getKey()])
        ->assertForbidden();
});
