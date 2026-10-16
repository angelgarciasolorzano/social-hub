<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * @return Testable<Component>
 */
function commentsRelationManagerFor(Post $post): Testable
{
    return Livewire::test(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => ViewPost::class,
    ]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lists only the selected post comments with their author and creation date', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $post = createPost($user);
    $otherPost = createPost($user);

    $selectedComment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment sentinel',
    ]);

    createComment($user, [
        'commentable_id' => $otherPost->getKey(),
        'commentable_type' => $otherPost->getMorphClass(),
        'content' => 'Foreign post comment sentinel',
    ]);

    commentsRelationManagerFor($post)
        ->assertOk()
        ->assertSeeText('Content')
        ->assertSeeText('Selected root comment sentinel')
        ->assertSeeText($user->name)
        ->assertSeeText($selectedComment->created_at?->format('M j, Y') ?? 'invalid creation date')
        ->assertDontSeeText('Foreign post comment sentinel');
});

it('paginates only the selected posts comments', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $commentAuthor = createUser();
    $otherPostAuthor = createUser();
    $post = createPost($user);
    $otherPost = createPost($otherPostAuthor);

    $firstTargetComment = createComment($commentAuthor, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Target comment 01',
    ]);

    $lastTargetComment = $firstTargetComment;

    foreach (range(2, 11) as $sequence) {
        $lastTargetComment = createComment($commentAuthor, [
            'commentable_id' => $post->getKey(),
            'commentable_type' => $post->getMorphClass(),
            'content' => 'Target comment '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }

    createComment($commentAuthor, [
        'commentable_id' => $otherPost->getKey(),
        'commentable_type' => $otherPost->getMorphClass(),
        'content' => 'Foreign post comment sentinel',
    ]);

    $testable = commentsRelationManagerFor($post)
        ->assertOk()
        ->assertSeeText('Target comment 11')
        ->assertDontSeeText('Foreign post comment sentinel');

    $component = FilamentTableRecords::table($testable);
    $firstPageComments = FilamentTableRecords::visibleRecords($component, Comment::class);

    expect($component->getAllTableRecordsCount())
        ->toBe(11)
        ->and($firstPageComments)
        ->toHaveCount(10)
        ->and(array_map(FilamentTableRecords::recordKey(...), $firstPageComments))
        ->toContain(FilamentTableRecords::recordKey($lastTargetComment))
        ->not->toContain(FilamentTableRecords::recordKey($firstTargetComment));

    $testable->call('setPage', 2)
        ->assertSeeText('Target comment 01')
        ->assertDontSeeText('Target comment 11');

    $secondPage = FilamentTableRecords::table($testable);
    $secondPageComments = FilamentTableRecords::visibleRecords($secondPage, Comment::class);

    expect(array_map(FilamentTableRecords::recordKey(...), $secondPageComments))
        ->toBe([FilamentTableRecords::recordKey($firstTargetComment)]);
});

it('filters the selected posts comments by creation date', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $otherPost = createPost();
    $commentInsideRange = createCommentOn($post, attributes: [
        'content' => 'Comment inside date range sentinel',
        'created_at' => '2026-01-15 12:00:00',
    ]);
    createCommentOn($post, attributes: [
        'content' => 'Comment outside date range sentinel',
        'created_at' => '2026-02-01 12:00:00',
    ]);
    createCommentOn($otherPost, attributes: [
        'content' => 'Foreign post comment inside date range sentinel',
        'created_at' => '2026-01-16 12:00:00',
    ]);

    $testable = commentsRelationManagerFor($post)
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20');
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($commentInsideRange)]);
});

it('filters the selected posts comments by author', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $otherAuthor = createUser();
    $post = createPost();
    $otherPost = createPost();
    $selectedComment = createCommentOn($post, $user, ['content' => 'Selected author comment sentinel']);
    createCommentOn($post, $otherAuthor, ['content' => 'Other author comment sentinel']);
    createCommentOn($otherPost, $user, ['content' => 'Foreign post author comment sentinel']);

    $testable = commentsRelationManagerFor($post)
        ->set('tableFilters.author.value', $user->getKey());
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($selectedComment)]);
});

it('filters the selected posts comments by reply presence', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $commentWithReplies = createCommentOn($post, attributes: ['content' => 'Comment with replies sentinel']);
    createCommentOn($commentWithReplies, attributes: ['content' => 'Reply for selected comment sentinel']);
    $commentWithoutReplies = createCommentOn($post, attributes: ['content' => 'Comment without replies sentinel']);

    $testable = commentsRelationManagerFor($post)
        ->set('tableFilters.has_replies.value', true);
    $withRepliesTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($withRepliesTable, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($commentWithReplies)]);

    $testable->set('tableFilters.has_replies.value', false);
    $withoutRepliesTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($withoutRepliesTable, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($commentWithoutReplies)]);
});

it('combines comment filters without showing other posts comments', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $otherAuthor = createUser();
    $post = createPost();
    $otherPost = createPost();

    $matchingComment = createCommentOn($post, $user, [
        'content' => 'Comment matching all filters sentinel',
        'created_at' => '2026-01-15 12:00:00',
    ]);
    createCommentOn($matchingComment, attributes: ['content' => 'Reply for matching comment sentinel']);

    createCommentOn($post, $otherAuthor, [
        'content' => 'Comment with another author sentinel',
        'created_at' => '2026-01-16 12:00:00',
    ]);
    createCommentOn($post, $user, [
        'content' => 'Comment with no replies sentinel',
        'created_at' => '2026-01-17 12:00:00',
    ]);
    createCommentOn($otherPost, $user, [
        'content' => 'Foreign post matching comment filters sentinel',
        'created_at' => '2026-01-18 12:00:00',
    ]);

    $testable = commentsRelationManagerFor($post)
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20')
        ->set('tableFilters.author.value', $user->getKey())
        ->set('tableFilters.has_replies.value', true);
    $filteredTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($filteredTable, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($matchingComment)]);

    $testable
        ->set('tableFilters.created_at.created_from', '2026-03-01')
        ->set('tableFilters.created_at.created_until', '2026-03-05');
    $emptyTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($emptyTable, Comment::class))
        ->toBeEmpty();
});

it('links each comment to its dedicated detail page', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $post = createPost();
    $otherPost = createPost();

    $selectedComment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment sentinel',
    ]);
    $foreignComment = createComment($user, [
        'commentable_id' => $otherPost->getKey(),
        'commentable_type' => $otherPost->getMorphClass(),
        'content' => 'Foreign post comment sentinel',
    ]);

    commentsRelationManagerFor($post)
        ->assertOk()
        ->assertSee(CommentResource::getUrl('view', ['record' => $selectedComment]), false)
        ->assertDontSee(CommentResource::getUrl('view', ['record' => $foreignComment]), false);
});

it('loads the selected comment details after following its detail link', function (): void {
    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost();

    $selectedComment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment for replies sentinel',
    ]);

    $detailUrl = CommentResource::getUrl('view', ['record' => $selectedComment]);

    commentsRelationManagerFor($post)
        ->assertOk()
        ->assertSee($detailUrl, false);

    $this->get($detailUrl)
        ->assertOk()
        ->assertSeeText('Selected root comment for replies sentinel');
});

it('does not load replies while listing comments', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $post = createPost();

    $comment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Comment with unloaded replies sentinel',
    ]);

    createComment(createUser(), [
        'commentable_id' => $comment->getKey(),
        'commentable_type' => $comment->getMorphClass(),
        'content' => 'Reply not loaded in table sentinel',
    ]);

    $component = commentsRelationManagerFor($post)
        ->assertOk()
        ->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The comments relation must expose a Filament table.');

    /** @var HasTable&Component $component */
    $comments = FilamentTableRecords::visibleRecords($component, Comment::class);

    expect($comments)->toHaveCount(1);

    foreach ($comments as $listedComment) {
        expect($listedComment->relationLoaded('comments'))->toBeFalse();
    }
});

it('keeps the comment view action read only', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();

    $component = commentsRelationManagerFor($post)
        ->assertOk()
        ->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The comments relation must expose a Filament table.');

    /** @var HasTable&Component $component */
    $table = $component->getTable();
    $recordActions = $table->getRecordActions();
    $viewAction = $recordActions[0] ?? null;

    throw_unless($viewAction instanceof ViewAction, LogicException::class, 'The comments relation must expose its view action.');

    expect($recordActions)
        ->toHaveCount(1)
        ->and($viewAction->hasModal())
        ->toBeFalse()
        ->and($viewAction->hasUrl())
        ->toBeTrue()
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('denies the comments relation to a user with only user permissions', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    expect($authenticatedUser->can(AdminPermission::AdminUsersViewAny->value))
        ->toBeTrue()
        ->and($authenticatedUser->can(AdminPermission::AdminPostsView->value))
        ->toBeFalse();

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    commentsRelationManagerFor($post)
        ->assertForbidden();
});
