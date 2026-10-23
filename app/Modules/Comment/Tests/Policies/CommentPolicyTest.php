<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Policies\CommentPolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * A user holding only the given Admin permission, through a role made for the test.
 */
function createUserWithCommentPermission(AdminPermission $adminPermission): User
{
    $role = Role::findOrCreate('comment-policy-test', 'admin');
    $role->givePermissionTo($adminPermission->value);

    $user = createUser();
    $user->assignRole($role);

    return $user;
}

it('resolves the CommentPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(createComment()))->toBeInstanceOf(CommentPolicy::class);
});

it('allows the author to :dataset', function (string $ability): void {
    $user = createUser();
    $comment = createComment($user);

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

it('denies another user to :dataset', function (string $ability): void {
    $comment = createComment(createUser());
    $user = createUser();

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

it('denies the author to :dataset a comment that was already deleted', function (string $ability): void {
    $user = createUser();
    $comment = CommentFactory::new()->for($user)->trashed()->createOne();

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

it('allows the author to :dataset a reply', function (string $ability): void {
    $user = createUser();
    $comment = createCommentOn(createComment(), $user);

    expect(Gate::forUser($user)->allows($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

it('allows :dataset with admin.posts.view', function (string $ability, bool $isRecordAbility): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUserWithCommentPermission(AdminPermission::AdminPostsView);

    expect(Gate::forUser($user)->allows($ability, $isRecordAbility ? createComment() : Comment::class))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', false],
    'view' => ['view', true],
]);

it('denies :dataset with only admin.posts.viewAny', function (string $ability, bool $isRecordAbility): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUserWithCommentPermission(AdminPermission::AdminPostsViewAny);

    expect(Gate::forUser($user)->allows($ability, $isRecordAbility ? createComment() : Comment::class))->toBeFalse();
})->with([
    'viewAny' => ['viewAny', false],
    'view' => ['view', true],
]);

it('denies :dataset to the author without an Admin permission', function (string $ability, bool $isRecordAbility): void {
    $user = createUser();
    $comment = createComment($user);

    expect(Gate::forUser($user)->allows($ability, $isRecordAbility ? $comment : Comment::class))->toBeFalse();
})->with([
    'viewAny' => ['viewAny', false],
    'view' => ['view', true],
]);
