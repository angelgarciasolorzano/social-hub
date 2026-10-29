<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Admin\Filament\Resources\Comments\RelationManagers\RepliesRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Comment\Models\Comment;
use App\User\Enums\PreferredLocale;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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

/**
 * @param  Testable<RepliesRelationManager>  $testable
 * @return Testable<RepliesRelationManager>
 */
function mountReplyViewAction(Testable $testable, Comment $comment): Testable
{
    return $testable->call('mountAction', 'viewReply', [], [
        'table' => true,
        'recordKey' => FilamentTableRecords::recordKey($comment),
    ]);
}

/**
 * @param  Testable<RepliesRelationManager>  $testable
 * @param  array<string>|string  $values
 */
function assertMountedReplyModalSee(Testable $testable, array|string $values): void
{
    $testable->__call('assertMountedActionModalSee', [$values]);
}

/**
 * @param  Testable<RepliesRelationManager>  $testable
 * @param  array<string>|string  $values
 */
function assertMountedReplyModalDoesNotSee(Testable $testable, array|string $values): void
{
    $testable->__call('assertMountedActionModalDontSee', [$values]);
}

/**
 * @return array{reply: string, author: string, created_at: string, creation_date: string, from: string, until: string, view: string, modal_heading: string, close: string}
 */
function expectedReplyTableLabels(PreferredLocale $preferredLocale): array
{
    return match ($preferredLocale) {
        PreferredLocale::ENGLISH => [
            'reply' => 'Reply',
            'author' => 'Author',
            'created_at' => 'Created at',
            'creation_date' => 'Creation date',
            'from' => 'From',
            'until' => 'Until',
            'view' => 'View reply',
            'modal_heading' => 'Reply details',
            'close' => 'Close',
        ],
        PreferredLocale::SPANISH => [
            'reply' => 'Respuesta',
            'author' => 'Autor',
            'created_at' => 'Fecha de creación',
            'creation_date' => 'Fecha de creación',
            'from' => 'Desde',
            'until' => 'Hasta',
            'view' => 'Ver respuesta',
            'modal_heading' => 'Detalles de la respuesta',
            'close' => 'Cerrar',
        ],
    };
}

/**
 * @return list<string>
 */
function expectedReplyModalLabels(PreferredLocale $preferredLocale): array
{
    return match ($preferredLocale) {
        PreferredLocale::ENGLISH => [
            'Reply details',
            'Reply content',
            'The complete message in this conversation.',
            'Message',
            'Author and timeline for this reply.',
            'Author',
            'Created at',
            'Edited at',
            'Replying to',
        ],
        PreferredLocale::SPANISH => [
            'Detalles de la respuesta',
            'Contenido de la respuesta',
            'El mensaje completo de esta conversación.',
            'Mensaje',
            'Autor y cronología de esta respuesta.',
            'Autor',
            'Fecha de creación',
            'Editado el',
            'En respuesta a',
        ],
    };
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('loads replies lazily and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $table = FilamentTableRecords::table(repliesRelationManagerFor(createComment(createUser())))->getTable();

    expect(RepliesRelationManager::isLazy())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeFalse()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
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

it('translates the replies table, filters and action in the active language', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    createCommentOn($parentComment, attributes: ['content' => 'Reply table translation sentinel']);

    $testable = repliesRelationManagerFor($parentComment)
        ->assertSeeText('Reply table translation sentinel');
    $table = FilamentTableRecords::table($testable)->getTable();
    $expectedLabels = expectedReplyTableLabels($preferredLocale);
    $columns = $table->getColumns();
    $dateFilter = $table->getFilter('created_at');

    throw_unless($dateFilter instanceof Filter, LogicException::class, 'The replies table must expose its creation date filter.');

    $dateFilterFields = $dateFilter->getSchemaComponents();
    $authorFilter = $table->getFilter('author');
    $viewAction = $table->getRecordActions()[0];

    throw_unless($dateFilterFields[0] instanceof DatePicker, LogicException::class, 'The creation date filter must expose a start date picker.');
    throw_unless($dateFilterFields[1] instanceof DatePicker, LogicException::class, 'The creation date filter must expose an end date picker.');
    throw_unless($authorFilter instanceof SelectFilter, LogicException::class, 'The replies table must expose its author filter.');
    throw_unless($viewAction instanceof Action, LogicException::class, 'The replies table must expose a view action.');

    expect($columns['content']->getLabel())
        ->toBe($expectedLabels['reply'])
        ->and($columns['user.name']->getLabel())
        ->toBe($expectedLabels['author'])
        ->and($columns['created_at']->getLabel())
        ->toBe($expectedLabels['created_at'])
        ->and($dateFilter->getLabel())
        ->toBe($expectedLabels['creation_date'])
        ->and($dateFilterFields[0]->getLabel())
        ->toBe($expectedLabels['from'])
        ->and($dateFilterFields[1]->getLabel())
        ->toBe($expectedLabels['until'])
        ->and($authorFilter->getLabel())
        ->toBe($expectedLabels['author'])
        ->and($viewAction->getLabel())
        ->toBe($expectedLabels['view'])
        ->and($viewAction->getModalHeading())
        ->toBe($expectedLabels['modal_heading'])
        ->and($viewAction->getModalCancelActionLabel())
        ->toBe($expectedLabels['close']);
})->with([
    'English' => PreferredLocale::ENGLISH,
    'Spanish' => PreferredLocale::SPANISH,
]);

it('does not resolve a reply from another comment when its ID is supplied', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $selectedComment = createCommentOn($post);
    $otherComment = createCommentOn($post);
    $comment = createCommentOn($otherComment, attributes: ['content' => 'Foreign reply by ID sentinel']);

    $table = FilamentTableRecords::table(repliesRelationManagerFor($selectedComment));

    expect($table->getTableRecord(FilamentTableRecords::recordKey($comment)))
        ->toBeNull();

    $testable = repliesRelationManagerFor($selectedComment)
        ->call('mountAction', 'viewReply', [], [
            'table' => true,
            'recordKey' => FilamentTableRecords::recordKey($comment),
        ])
        ->assertDontSeeText('Foreign reply by ID sentinel');

    expect($testable->instance()->mountedActions)
        ->toBeEmpty();
});

it('opens a read only modal with the full reply and available context', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    $user = createUser();
    $mentionedUser = createUser();
    $fullReply = str_repeat('Full reply context sentinel ', 5);
    $comment = createCommentOn($parentComment, $user, [
        'content' => $fullReply,
        'created_at' => '2026-10-25 14:35:00',
        'edited_at' => '2026-10-26 08:45:00',
        'replied_to_user_id' => $mentionedUser->getKey(),
    ]);

    $testable = mountReplyViewAction(repliesRelationManagerFor($parentComment), $comment);

    assertMountedReplyModalSee($testable, [
        'Reply content',
        'Reply details',
        'Message',
        $fullReply,
        $user->name,
        '2026-10-25 14:35',
        '2026-10-26 08:45',
        'Replying to',
        "@{$mentionedUser->name}",
    ]);
});

it('translates the reply modal while preserving user authored content', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    $user = createUser();
    $mentionedUser = createUser();
    $replyContent = 'Reply modal translation sentinel';
    $comment = createCommentOn($parentComment, $user, [
        'content' => $replyContent,
        'edited_at' => '2026-10-26 08:45:00',
        'replied_to_user_id' => $mentionedUser->getKey(),
    ]);

    $testable = mountReplyViewAction(repliesRelationManagerFor($parentComment), $comment);

    assertMountedReplyModalSee($testable, expectedReplyModalLabels($preferredLocale));
    assertMountedReplyModalSee($testable, [$replyContent, $user->name, "@{$mentionedUser->name}"]);
})->with([
    'English' => PreferredLocale::ENGLISH,
    'Spanish' => PreferredLocale::SPANISH,
]);

it('omits edited time and reply context when the reply has neither', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    $comment = createCommentOn($parentComment, attributes: ['content' => 'Unedited standalone reply sentinel']);

    $testable = mountReplyViewAction(repliesRelationManagerFor($parentComment), $comment);

    assertMountedReplyModalSee($testable, 'Unedited standalone reply sentinel');
    assertMountedReplyModalDoesNotSee($testable, ['Edited at', 'Replying to']);
});

it('opens the reply modal when its mentioned user has been deleted', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $parentComment = createCommentOn(createPost());
    $mentionedUser = createUser();
    $comment = createCommentOn($parentComment, attributes: [
        'content' => 'Reply with deleted mention sentinel',
        'replied_to_user_id' => $mentionedUser->getKey(),
    ]);

    $mentionedUser->delete();

    $testable = mountReplyViewAction(repliesRelationManagerFor($parentComment), $comment);

    assertMountedReplyModalSee($testable, 'Reply with deleted mention sentinel');
    assertMountedReplyModalDoesNotSee($testable, 'Replying to');
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

    $testable = repliesRelationManagerFor($selectedComment)->assertOk();
    $repliesRelationManager = $testable->instance();

    $table = $repliesRelationManager->getTable();
    $recordActions = $table->getRecordActions();

    expect($recordActions)
        ->toHaveCount(1)
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();

    $viewAction = $recordActions[0];

    throw_unless($viewAction instanceof Action, LogicException::class, 'The replies table must expose a single read action.');

    expect($viewAction->getName())
        ->toBe('viewReply');
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
