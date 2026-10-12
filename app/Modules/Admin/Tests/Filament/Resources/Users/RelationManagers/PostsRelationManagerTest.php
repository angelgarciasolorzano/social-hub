<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\PostsRelationManager;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Post\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('registers posts as a user resource relation', function (): void {
    expect(UserResource::getRelations())
        ->toContain(PostsRelationManager::class);
});

it('lists only the selected users posts with detail links', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $otherUser = createUser();
    $targetPost = createPost($targetUser, ['content' => 'Selected user post sentinel']);
    $foreignPost = createPost($otherUser, ['content' => 'Foreign user post sentinel']);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSee(PostResource::getUrl('view', ['record' => $targetPost]), false)
        ->assertSeeText('Selected user post sentinel')
        ->assertDontSee(PostResource::getUrl('view', ['record' => $foreignPost]), false)
        ->assertDontSeeText('Foreign user post sentinel');
});

it('paginates only the selected users posts', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $firstTargetPost = createPost($targetUser, ['content' => 'Target post 01']);
    $lastTargetPost = $firstTargetPost;

    foreach (range(2, 11) as $sequence) {
        $lastTargetPost = createPost($targetUser, [
            'content' => 'Target post '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSeeText('Target post 11')
        ->assertDontSeeText('Target post 01')
        ->assertSeeText('Showing 1 to 10 of 11 results')
        ->call('setPage', 2)
        ->assertSeeText('Target post 01')
        ->assertDontSeeText('Target post 11');
});

it('links user posts to their read-only detail and hides their comments', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    $targetPost = createPost($targetUser, ['content' => 'Selected post content sentinel']);
    createCommentOn($targetPost, attributes: ['content' => 'Root comment sentinel']);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertSee(PostResource::getUrl('view', ['record' => $targetPost]), false)
        ->assertDontSeeText('Root comment sentinel');
});

it('keeps the posts relation read only', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $targetUser = createUser();
    createPost($targetUser);

    $this->actingAs($authenticatedUser, 'admin');

    $component = Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The posts component must expose a Filament table.');

    /** @var HasTable&Component $component */
    $table = $component->getTable();

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

    /** @var HasTable&Component $tableComponent */
    $tableComponent = $component;

    foreach (FilamentTableRecords::visibleRecords($tableComponent, Post::class) as $visiblePost) {
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

    /** @var HasTable&Component $component */
    expect($component->getAllTableRecordsCount())
        ->toBe(2)
        ->and(FilamentTableRecords::visibleRecordKeys($component, Post::class))
        ->toContain(FilamentTableRecords::recordKey($alphaPost), FilamentTableRecords::recordKey($zetaPost))
        ->not->toContain(FilamentTableRecords::recordKey($foreignPost));

    $testable->call('sortTable', 'content', 'asc');

    $sortedRelationManager = $testable->instance();

    throw_unless($sortedRelationManager instanceof HasTable, LogicException::class, 'The sorted posts component must expose a Filament table.');

    /** @var HasTable&Component $sortedRelationManager */
    expect(FilamentTableRecords::visibleRecordKeys($sortedRelationManager, Post::class))
        ->toBe([
            FilamentTableRecords::recordKey($alphaPost),
            FilamentTableRecords::recordKey($zetaPost),
        ]);
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

    /** @var HasTable&Component $relationManager */
    expect(FilamentTableRecords::visibleRecordKeys($relationManager, Post::class))
        ->toBe([FilamentTableRecords::recordKey($post)]);

    $testable->set('tableFilters.has_comments.value', false);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The filtered posts component must expose a Filament table.');

    /** @var HasTable&Component $relationManager */
    expect(FilamentTableRecords::visibleRecordKeys($relationManager, Post::class))
        ->toBe([FilamentTableRecords::recordKey($withinRangeWithoutComments)]);
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
