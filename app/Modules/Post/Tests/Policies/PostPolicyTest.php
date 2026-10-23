<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Policies\PostPolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * A user holding only the given Admin permissions, through a role made for the test.
 */
function createUserWithPostPermissions(AdminPermission ...$adminPermissions): User
{
    $role = Role::findOrCreate('post-policy-test', 'admin');
    $role->givePermissionTo(array_map(fn (AdminPermission $adminPermission): string => $adminPermission->value, $adminPermissions));

    $user = createUser();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('resolves PostPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(Post::class))->toBeInstanceOf(PostPolicy::class);
});

it('allows listing posts only with admin.posts.viewAny', function (): void {
    $user = createUserWithPostPermissions(AdminPermission::AdminPostsViewAny);

    expect(Gate::forUser($user)->allows('viewAny', Post::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', createPost()))->toBeFalse();
});

it('allows opening a post only with admin.posts.view', function (): void {
    $user = createUserWithPostPermissions(AdminPermission::AdminPostsView);

    expect(Gate::forUser($user)->allows('view', createPost()))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewAny', Post::class))->toBeFalse();
});

it('allows opening posts in general with admin.posts.view when no post is given', function (): void {
    $user = createUserWithPostPermissions(AdminPermission::AdminPostsView);

    expect(Gate::forUser($user)->allows('view', Post::class))->toBeTrue();
});

it('denies opening a post that no longer has an author', function (): void {
    $user = createUserWithPostPermissions(AdminPermission::AdminPostsView);
    $post = createPost()->setRelation('user', null);

    expect(Gate::forUser($user)->allows('view', $post))->toBeFalse();
});

it('denies both read abilities without a posts permission', function (): void {
    $user = createUserWithPostPermissions(AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersView);

    expect(Gate::forUser($user)->allows('viewAny', Post::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', createPost()))->toBeFalse();
});

it('denies :dataset to a posts reader who wrote the post', function (string $ability, bool $isRecordAbility): void {
    $user = createUser();
    $user->assignRole([AdminRole::Admin->value, AdminRole::AdminPostsReader->value]);

    $post = createPost($user);

    expect(Gate::forUser($user)->allows($ability, $isRecordAbility ? $post : Post::class))->toBeFalse();
})->with([
    'create' => ['create', false],
    'update' => ['update', true],
    'delete' => ['delete', true],
    'deleteAny' => ['deleteAny', false],
    'restore' => ['restore', true],
    'restoreAny' => ['restoreAny', false],
    'forceDelete' => ['forceDelete', true],
    'forceDeleteAny' => ['forceDeleteAny', false],
]);
