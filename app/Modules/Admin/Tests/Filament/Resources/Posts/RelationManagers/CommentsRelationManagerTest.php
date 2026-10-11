<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
use App\Modules\Comment\Models\Comment;
use Carbon\CarbonImmutable;
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

it('lists only the selected posts comments with pagination without loading replies', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $user = createUser();
    $commentAuthor = createUser();
    $replyAuthor = createUser();
    $otherPostAuthor = createUser();
    $post = createPost($user);
    $otherPost = createPost($otherPostAuthor);
    $targetComments = collect();

    foreach (range(1, 11) as $sequence) {
        $targetComments->push(createComment($commentAuthor, [
            'commentable_id' => $post->getKey(),
            'commentable_type' => $post->getMorphClass(),
            'content' => 'Target comment '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]));
    }

    $firstTargetComment = $targetComments->first();
    $lastTargetComment = $targetComments->last();

    throw_unless($firstTargetComment instanceof Comment, LogicException::class, 'The first target comment must exist.');
    throw_unless($lastTargetComment instanceof Comment, LogicException::class, 'The last target comment must exist.');

    createComment($replyAuthor, [
        'commentable_id' => $firstTargetComment->getKey(),
        'commentable_type' => $firstTargetComment->getMorphClass(),
        'content' => 'Reply loaded only from the view action sentinel',
    ]);

    createComment($commentAuthor, [
        'commentable_id' => $otherPost->getKey(),
        'commentable_type' => $otherPost->getMorphClass(),
        'content' => 'Foreign post comment sentinel',
    ]);

    $createdAt = $lastTargetComment->created_at;

    throw_unless($createdAt instanceof CarbonImmutable, LogicException::class, 'The target comment creation timestamp must exist.');

    $this->actingAs($authenticatedUser, 'admin');

    expect(PostResource::getRelations())
        ->toContain(CommentsRelationManager::class);

    $testable = Livewire::test(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => ViewPost::class,
    ])
        ->assertOk()
        ->assertSeeText('Content')
        ->assertSeeText('Target comment 11')
        ->assertSeeText($commentAuthor->name)
        ->assertSeeText($createdAt->format('M j, Y'))
        ->assertDontSeeText('Reply loaded only from the view action sentinel')
        ->assertDontSeeText('Foreign post comment sentinel');

    $component = $testable->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The comments relation must expose a Filament table.');

    $firstPageComments = visibleCommentRecords($component);
    $firstPageCommentIds = array_map(commentResourceKey(...), $firstPageComments);

    expect($component->getAllTableRecordsCount())
        ->toBe(11)
        ->and($firstPageComments)
        ->toHaveCount(10)
        ->and($firstPageCommentIds)
        ->toContain(commentResourceKey($lastTargetComment))
        ->not->toContain(commentResourceKey($firstTargetComment));

    foreach ($firstPageComments as $firstPageComment) {
        expect($firstPageComment->relationLoaded('comments'))->toBeFalse();
    }

    $testable->call('setPage', 2)
        ->assertSeeText('Target comment 01')
        ->assertDontSeeText('Target comment 11');

    $secondPage = $testable->instance();

    throw_unless($secondPage instanceof HasTable, LogicException::class, 'The comments second page must expose a Filament table.');

    $secondPageComments = visibleCommentRecords($secondPage);

    expect(array_map(commentResourceKey(...), $secondPageComments))
        ->toBe([commentResourceKey($firstTargetComment)]);

    foreach ($secondPageComments as $secondPageComment) {
        expect($secondPageComment->relationLoaded('comments'))->toBeFalse();
    }
});

it('opens a comment with its replies and authors in a read only view action', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $user = createUser();
    $commentAuthor = createUser();
    $replyAuthor = createUser();
    $post = createPost($user);

    $selectedComment = createComment($commentAuthor, [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'content' => 'Selected root comment sentinel',
    ]);

    $reply = createComment($replyAuthor, [
        'commentable_id' => $selectedComment->getKey(),
        'commentable_type' => $selectedComment->getMorphClass(),
        'content' => 'Selected reply sentinel',
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => ViewPost::class,
    ])
        ->assertOk();

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof HasTable, LogicException::class, 'The comments relation must expose a Filament table.');

    foreach (visibleCommentRecords($relationManager) as $comment) {
        expect($comment->relationLoaded('comments'))->toBeFalse();
    }

    $testable->call('mountAction', 'view', [], [
        'table' => true,
        'recordKey' => $selectedComment->getKey(),
    ]);

    $relationManager = $testable->instance();

    throw_unless($relationManager instanceof CommentsRelationManager, LogicException::class, 'The comments relation must expose its mounted view action.');

    $viewActionData = mountedCommentViewActionData($relationManager);
    $replies = $viewActionData['replies'] ?? null;
    $replyData = is_array($replies) ? ($replies[0] ?? null) : null;

    throw_unless(is_array($replyData), LogicException::class, 'The selected comment must include its reply data.');

    expect($viewActionData)
        ->toMatchArray([
            'content' => 'Selected root comment sentinel',
            'user' => ['name' => $commentAuthor->name],
        ])
        ->and($replies)
        ->toHaveCount(1)
        ->and($replyData)
        ->toMatchArray([
            'content' => 'Selected reply sentinel',
            'user' => ['name' => $replyAuthor->name],
            'created_at' => $reply->created_at?->toDateTimeString(),
        ]);

    $table = $relationManager->getTable();
    $recordActions = $table->getRecordActions();
    $viewAction = $recordActions[0] ?? null;

    throw_unless($viewAction instanceof ViewAction, LogicException::class, 'The comments relation must expose its view action.');

    expect($recordActions)
        ->toHaveCount(1)
        ->and($viewAction->getModalHeading())
        ->toBe('Comment details')
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('denies the comments relation without users view even when users view any is granted', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    $authenticatedUser->givePermissionTo(AdminPermission::UsersViewAny->value);

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => ViewPost::class,
    ])
        ->assertForbidden();
});

/**
 * @return list<Comment>
 */
function visibleCommentRecords(HasTable $hasTable): array
{
    $records = $hasTable->getTableRecords();

    $pageRecords = match (true) {
        $records instanceof Paginator,
        $records instanceof CursorPaginator => $records->items(),
        $records instanceof Collection => $records->all(),
    };

    return array_values(array_map(static function (mixed $record): Comment {
        throw_unless($record instanceof Comment, LogicException::class, 'The comments relation must only return Comment records.');

        return $record;
    }, $pageRecords));
}

function commentResourceKey(Comment $comment): string
{
    $key = $comment->getKey();

    throw_unless(is_int($key) || is_string($key), LogicException::class, 'Comment records must use string or integer keys.');

    return (string) $key;
}

/**
 * @return array<string, mixed>
 */
function mountedCommentViewActionData(CommentsRelationManager $commentsRelationManager): array
{
    $mountedAction = $commentsRelationManager->mountedActions[0] ?? null;

    throw_unless(is_array($mountedAction), LogicException::class, 'The comments relation must mount a view action.');

    $data = $mountedAction['data'] ?? null;

    throw_unless(is_array($data), LogicException::class, 'The comment view action must include its record data.');

    $stringKeyedData = [];

    foreach ($data as $key => $value) {
        if (is_string($key)) {
            $stringKeyedData[$key] = $value;
        }
    }

    return $stringKeyedData;
}
