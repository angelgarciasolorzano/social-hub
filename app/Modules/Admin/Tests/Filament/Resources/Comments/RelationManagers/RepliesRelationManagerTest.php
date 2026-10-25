<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Admin\Filament\Resources\Comments\RelationManagers\RepliesRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Comment\Models\Comment;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return Testable<RepliesRelationManager>
 */
function repliesRelationManagerFor(Comment $comment): Testable
{
    return Livewire::test(RepliesRelationManager::class, [
        'ownerRecord' => $comment,
        'pageClass' => ViewComment::class,
    ]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('renders only replies for the selected comment with their authors', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $selectedComment = createCommentOn($post, attributes: ['content' => 'Selected parent comment']);
    $otherComment = createCommentOn($post, attributes: ['content' => 'Other parent comment']);
    $user = createUser();
    $comment = createCommentOn($selectedComment, $user, ['content' => 'Selected reply sentinel']);
    createCommentOn($otherComment, attributes: ['content' => 'Foreign reply sentinel']);

    $testable = repliesRelationManagerFor($selectedComment)
        ->assertOk()
        ->assertSeeText('Selected reply sentinel')
        ->assertSeeText($user->name)
        ->assertDontSeeText('Foreign reply sentinel');
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($comment)]);
});

it('truncates long reply previews in the replies table', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    $content = str_repeat('This reply preview should stay concise in the table. ', 5);
    createCommentOn($parentComment, attributes: ['content' => $content]);

    repliesRelationManagerFor($parentComment)
        ->assertSeeText(rtrim(mb_substr($content, 0, 70)))
        ->assertDontSeeText($content);
});

it('does not resolve a reply from another comment when its ID is supplied', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $selectedComment = createCommentOn($post);
    $otherComment = createCommentOn($post);
    $comment = createCommentOn($otherComment, attributes: ['content' => 'Foreign reply by ID sentinel']);

    $table = FilamentTableRecords::table(repliesRelationManagerFor($selectedComment));

    expect($table->getTableRecord(FilamentTableRecords::recordKey($comment)))
        ->toBeNull();
});

it('paginates the selected comments replies', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $selectedComment = createCommentOn($post);
    $selectedReplies = createCommentsOn($selectedComment, 11);

    $testable = repliesRelationManagerFor($selectedComment)
        ->assertOk()
        ->assertSeeText('Showing 1 to 10 of 11 results');
    $repliesRelationManager = FilamentTableRecords::table($testable);

    expect($repliesRelationManager->getAllTableRecordsCount())
        ->toBe(11)
        ->and(FilamentTableRecords::visibleRecords($repliesRelationManager, Comment::class))
        ->toHaveCount(10);

    $testable->call('setPage', 2)
        ->assertSeeText('Showing 11 to 11 of 11 results');
    $secondPageTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($secondPageTable, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($selectedReplies[0])]);
});

it('filters the selected comments replies by creation date', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $otherPost = createPost();
    $selectedComment = createCommentOn($post);
    $otherComment = createCommentOn($otherPost);
    $comment = createCommentOn($selectedComment, attributes: [
        'content' => 'Reply inside date range sentinel',
        'created_at' => '2026-01-15 12:00:00',
    ]);
    createCommentOn($selectedComment, attributes: [
        'content' => 'Reply outside date range sentinel',
        'created_at' => '2026-02-01 12:00:00',
    ]);
    createCommentOn($otherComment, attributes: [
        'content' => 'Foreign reply inside date range sentinel',
        'created_at' => '2026-01-16 12:00:00',
    ]);

    $testable = repliesRelationManagerFor($selectedComment)
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20');
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($comment)]);
});

it('filters the selected comments replies by author', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $otherPost = createPost();
    $selectedComment = createCommentOn($post);
    $otherComment = createCommentOn($otherPost);
    $user = createUser();
    $comment = createCommentOn($selectedComment, $user, ['content' => 'Selected author reply sentinel']);
    createCommentOn($selectedComment, createUser(), ['content' => 'Other author reply sentinel']);
    createCommentOn($otherComment, $user, ['content' => 'Foreign author reply sentinel']);

    $testable = repliesRelationManagerFor($selectedComment)
        ->set('tableFilters.author.value', $user->getKey());
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Comment::class))
        ->toBe([FilamentTableRecords::recordKey($comment)]);
});

it('keeps the replies table read only', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $selectedComment = createCommentOn($post);
    createCommentOn($selectedComment);

    $repliesRelationManager = repliesRelationManagerFor($selectedComment)
        ->assertOk()
        ->instance();

    $table = $repliesRelationManager->getTable();

    expect($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('renders the replies manager on comment details', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();
    $comment = createCommentOn($post);

    $this->actingAs($authenticatedUser, 'admin');

    $component = Livewire::test(ViewComment::class, ['record' => $comment->getKey()])
        ->assertOk()
        ->instance();

    throw_unless($component instanceof ViewComment, LogicException::class, 'The comment detail must resolve its Filament ViewComment page.');

    /** @var ViewComment $component */
    expect($component->getRelationManagers())
        ->toContain(RepliesRelationManager::class);
});
