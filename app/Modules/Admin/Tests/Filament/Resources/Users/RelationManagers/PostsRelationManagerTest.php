<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\PostsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Post\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use LogicException;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');
});

it('lists only the selected users posts with pagination', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $otherUser = createUser();

    $targetPosts = collect([createPost($targetUser, ['content' => 'Target post 01'])]);

    foreach (range(2, 11) as $sequence) {
        $targetPosts->push(createPost($targetUser, [
            'content' => 'Target post '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]));
    }

    $firstTargetPost = $targetPosts->first();
    $otherUsersPost = createPost($otherUser, ['content' => 'Foreign post sentinel']);
    $lastTargetPost = $targetPosts->last();

    throw_unless($firstTargetPost instanceof Post, LogicException::class, 'The selected user must have a post to paginate.');
    throw_unless($lastTargetPost instanceof Post, LogicException::class, 'The selected user must have a post on the first page.');

    $this->actingAs($authenticatedUser, 'admin');

    expect(UserResource::getRelations())
        ->toContain(PostsRelationManager::class);

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSee(PostResource::getUrl('view', ['record' => $lastTargetPost]), false);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The posts component must expose a Filament table.');

    $firstPagePostIds = visiblePostIds($relationManager);

    expect($relationManager->getAllTableRecordsCount())
        ->toBe(11)
        ->and($firstPagePostIds)
        ->toHaveCount(10)
        ->toContain(postKey($lastTargetPost))
        ->not->toContain(postKey($firstTargetPost))
        ->not->toContain(postKey($otherUsersPost));

    $testable->call('setPage', 2);
    $testable->assertSee(PostResource::getUrl('view', ['record' => $firstTargetPost]), false);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The posts component must expose a Filament table.');

    expect(visiblePostIds($relationManager))
        ->toBe([postKey($firstTargetPost)]);
});

it('links posts to the dedicated read-only detail without loading their comment threads', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $user = createUser();
    $replyAuthor = createUser();
    $otherUser = createUser();

    $targetPost = createPost($targetUser, ['content' => 'Selected post content sentinel']);
    $otherUsersPost = createPost($otherUser, ['content' => 'Foreign post content sentinel']);

    $comment = createComment($user, [
        'commentable_id' => $targetPost->getKey(),
        'commentable_type' => $targetPost->getMorphClass(),
        'content' => 'Root comment sentinel',
    ]);

    createComment($replyAuthor, [
        'commentable_id' => $comment->getKey(),
        'commentable_type' => $comment->getMorphClass(),
        'content' => 'Reply sentinel',
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSee(PostResource::getUrl('view', ['record' => $targetPost]), false)
        ->assertDontSeeText('Root comment sentinel')
        ->assertDontSeeText('Reply sentinel');

    $relationManagerComponent = $testable->instance();

    throw_unless($relationManagerComponent instanceof HasTable, LogicException::class, 'The posts component must expose a Filament table.');

    $visiblePosts = visiblePostRecords($relationManagerComponent);

    expect(array_map(postKey(...), $visiblePosts))
        ->toContain(postKey($targetPost))
        ->not->toContain(postKey($otherUsersPost));

    foreach ($visiblePosts as $visiblePost) {
        expect($visiblePost->relationLoaded('comments'))->toBeFalse();
    }

    $table = $relationManagerComponent->getTable();
    $recordActions = $table->getRecordActions();

    expect($recordActions)
        ->toHaveCount(1)
        ->and($recordActions[0])
        ->toBeInstanceOf(ViewAction::class)
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('does not load comment threads for posts with many comments and replies', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $user = createUser();
    $replyAuthor = createUser();

    $post = createPost($targetUser);

    foreach (range(1, 8) as $sequence) {
        $comment = createComment($user, [
            'commentable_id' => $post->getKey(),
            'commentable_type' => $post->getMorphClass(),
            'content' => 'Comment '.$sequence,
        ]);

        createComment($replyAuthor, [
            'commentable_id' => $comment->getKey(),
            'commentable_type' => $comment->getMorphClass(),
            'content' => 'Reply '.$sequence,
        ]);
    }

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ]);

    $component = $testable->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The posts relation must expose a Filament table.');

    foreach (visiblePostRecords($component) as $visiblePost) {
        expect($visiblePost->relationLoaded('comments'))->toBeFalse();
    }
});

it('searches the selected users posts and sorts the content column', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $otherUser = createUser();
    $zetaPost = createPost($targetUser, ['content' => 'Target search Zeta']);
    $alphaPost = createPost($targetUser, ['content' => 'Target search Alpha']);
    createPost($targetUser, ['content' => 'Unrelated target post']);
    $foreignPost = createPost($otherUser, ['content' => 'Target search Foreign']);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->set('tableSearch', 'Target search');

    $component = $testable->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The posts component must expose a Filament table.');

    expect($component->getAllTableRecordsCount())
        ->toBe(2)
        ->and(visiblePostIds($component))
        ->toContain(postKey($alphaPost), postKey($zetaPost))
        ->not->toContain(postKey($foreignPost));

    $testable->call('sortTable', 'content', 'asc');

    $sortedRelationManager = $testable->instance();

    throw_unless($sortedRelationManager instanceof HasTable, LogicException::class, 'The sorted posts component must expose a Filament table.');

    expect(visiblePostIds($sortedRelationManager))
        ->toBe([postKey($alphaPost), postKey($zetaPost)]);
});

it('filters the selected users posts by comment presence and creation date', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $user = createUser();

    $post = createPost($targetUser, ['content' => 'Inside date range with comments']);
    $post->forceFill(['created_at' => '2026-01-15 12:00:00'])->save();

    createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Comment in range',
    ]);

    $withinRangeWithoutComments = createPost($targetUser, ['content' => 'Inside date range without comments']);
    $withinRangeWithoutComments->forceFill(['created_at' => '2026-01-18 12:00:00'])->save();

    $outsideRangeWithComments = createPost($targetUser, ['content' => 'Outside date range with comments']);
    $outsideRangeWithComments->forceFill(['created_at' => '2026-02-01 12:00:00'])->save();

    createComment($user, [
        'commentable_id' => $outsideRangeWithComments->getKey(),
        'commentable_type' => $outsideRangeWithComments->getMorphClass(),
        'content' => 'Comment outside range',
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20')
        ->set('tableFilters.has_comments.value', true);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The filtered posts component must expose a Filament table.');

    expect(visiblePostIds($relationManager))
        ->toBe([postKey($post)]);

    $testable->set('tableFilters.has_comments.value', false);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The filtered posts component must expose a Filament table.');

    expect(visiblePostIds($relationManager))
        ->toBe([postKey($withinRangeWithoutComments)]);
});

it('forbids users without Posts permission from viewing the relation', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertForbidden();
});

it('forbids direct relation requests without User record access', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertForbidden();
});

/**
 * @return list<string>
 */
function visiblePostIds(HasTable $hasTable): array
{
    return array_map(postKey(...), visiblePostRecords($hasTable));
}

/**
 * @return list<Post>
 */
function visiblePostRecords(HasTable $hasTable): array
{
    $records = $hasTable->getTableRecords();

    $pageRecords = match (true) {
        $records instanceof Paginator,
        $records instanceof CursorPaginator => $records->items(),
        $records instanceof Collection => $records->all(),
    };

    return array_values(array_map(static function (mixed $record): Post {
        throw_unless($record instanceof Post, LogicException::class, 'The posts relation must only return Post records.');

        return $record;
    }, $pageRecords));
}

function postKey(Post $post): string
{
    $key = $post->getKey();

    throw_unless(is_int($key) || is_string($key), LogicException::class, 'Post records must use string or integer keys.');

    return (string) $key;
}
