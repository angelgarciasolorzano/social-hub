<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
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

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

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

it('opens a comment with its author in the view action', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $post = createPost();

    $selectedComment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment sentinel',
    ]);

    $testable = commentsRelationManagerFor($post)
        ->assertOk()
        ->call('mountAction', 'view', [], [
            'table' => true,
            'recordKey' => $selectedComment->getKey(),
        ]);

    $component = $testable->instance();

    throw_unless($component instanceof CommentsRelationManager, LogicException::class, 'The comments relation must expose its mounted view action.');

    /** @var CommentsRelationManager $component */
    $viewActionData = mountedCommentViewActionData($component);

    expect($viewActionData)->toMatchArray([
        'content' => 'Selected root comment sentinel',
        'user' => ['name' => $user->name],
    ]);
});

it('opens a comment with its replies and their authors in the view action', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $user = createUser();
    $replyAuthor = createUser();
    $post = createPost();

    $selectedComment = createComment($user, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment for replies sentinel',
    ]);

    createComment($replyAuthor, [
        'commentable_id' => $selectedComment->getKey(),
        'commentable_type' => $selectedComment->getMorphClass(),
        'content' => 'Selected reply sentinel',
    ]);

    $testable = commentsRelationManagerFor($post)
        ->assertOk()
        ->call('mountAction', 'view', [], [
            'table' => true,
            'recordKey' => $selectedComment->getKey(),
        ]);

    $component = $testable->instance();

    throw_unless($component instanceof CommentsRelationManager, LogicException::class, 'The comments relation must expose its mounted view action.');

    /** @var CommentsRelationManager $component */
    $viewActionData = mountedCommentViewActionData($component);

    $replies = $viewActionData['replies'] ?? null;
    $replyData = is_array($replies) ? ($replies[0] ?? null) : null;

    throw_unless(is_array($replies) && is_array($replyData), LogicException::class, 'The selected comment must include its reply data.');

    /** @var array<int, mixed> $replies */
    /** @var array<string, mixed> $replyData */
    expect($replies)->toHaveCount(1)
        ->and($replyData)
        ->toMatchArray([
            'content' => 'Selected reply sentinel',
            'user' => ['name' => $replyAuthor->name],
        ]);
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

    /** @var ViewAction $viewAction */
    expect($recordActions)
        ->toHaveCount(1)
        ->and($viewAction->getModalHeading())
        ->toBe('Comment details')
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

/**
 * @return array<string, mixed>
 */
function mountedCommentViewActionData(CommentsRelationManager $commentsRelationManager): array
{
    $mountedAction = $commentsRelationManager->mountedActions[0] ?? null;

    throw_unless(is_array($mountedAction), LogicException::class, 'The comments relation must mount a view action.');

    /** @var array<int|string, mixed> $mountedAction */
    $data = $mountedAction['data'] ?? null;

    throw_unless(is_array($data), LogicException::class, 'The comment view action must include its record data.');

    /** @var array<int|string, mixed> $data */
    $stringKeyedData = [];

    foreach ($data as $key => $value) {
        if (is_string($key)) {
            $stringKeyedData[$key] = $value;
        }
    }

    return $stringKeyedData;
}
