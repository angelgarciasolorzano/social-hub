<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('renders the selected comment and its author for Posts readers', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost();
    $comment = createCommentOn($post, $user, ['content' => 'Selected comment detail sentinel']);

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(CommentResource::getUrl('view', ['record' => $comment]))
        ->assertOk()
        ->assertSeeText('Selected comment detail sentinel')
        ->assertSeeText($user->name);
});

it('registers only the hidden direct detail page', function (): void {
    expect(CommentResource::shouldRegisterNavigation())
        ->toBeFalse()
        ->and(array_keys(CommentResource::getPages()))
        ->toBe(['view']);
});

it('denies comment details to users without Posts view permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();
    $comment = createCommentOn($post);

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(CommentResource::getUrl('view', ['record' => $comment]))
        ->assertForbidden();
});

it('returns not found for a missing comment record', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(CommentResource::getUrl('view', ['record' => PHP_INT_MAX]))
        ->assertNotFound();
});
