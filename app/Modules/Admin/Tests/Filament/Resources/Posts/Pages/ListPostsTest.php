<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Post\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** @return Testable<ListPosts> */
function postsListPageForTesting(): Testable
{
    return Livewire::test(ListPosts::class)->call('loadTable');
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('defers loading the posts table and displays a loading skeleton', function (): void {
    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminPostsReader,
    );
    $this->actingAs($authenticatedUser, 'admin');

    $component = Livewire::test(ListPosts::class)->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The Posts index must expose a Filament table.');

    $table = $component->getTable();

    expect($table->isLoadingDeferred())->toBeTrue()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
});

it('lists posts with pagination and content search', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $user = createUser();
    $targetPosts = collect([createPost($user, ['content' => 'Target post 01'])]);

    foreach (range(2, 11) as $sequence) {
        $targetPosts->push(createPost($user, [
            'content' => 'Target post '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]));
    }

    $otherAuthor = createUser();
    $otherAuthorsPost = createPost($otherAuthor, ['content' => 'Other author post sentinel']);
    $firstTargetPost = $targetPosts->first();
    $lastTargetPost = $targetPosts->last();

    throw_unless($firstTargetPost instanceof Post, LogicException::class, 'The first target post must exist.');

    /** @var Post $firstTargetPost */
    throw_unless($lastTargetPost instanceof Post, LogicException::class, 'The last target post must exist.');

    /** @var Post $lastTargetPost */
    $this->actingAs($authenticatedUser, 'admin');

    $testable = postsListPageForTesting()
        ->assertOk()
        ->assertSeeText('Target post 11')
        ->assertSeeText($user->name)
        ->assertSeeText('Other author post sentinel');

    $listPosts = $testable->instance();

    expect($listPosts->getAllTableRecordsCount())
        ->toBe(12)
        ->and(FilamentTableRecords::visibleRecordKeys($listPosts, Post::class))
        ->not->toContain(FilamentTableRecords::recordKey($firstTargetPost));

    $testable->call('setPage', 2);

    $secondPage = $testable->instance();

    expect(FilamentTableRecords::visibleRecordKeys($secondPage, Post::class))
        ->toContain(FilamentTableRecords::recordKey($firstTargetPost));

    $searchTest = postsListPageForTesting()
        ->set('tableSearch', 'Target post 01');

    $searchPage = $searchTest->instance();

    expect(FilamentTableRecords::visibleRecordKeys($searchPage, Post::class))
        ->toContain(FilamentTableRecords::recordKey($firstTargetPost))
        ->not->toContain(
            FilamentTableRecords::recordKey($lastTargetPost),
            FilamentTableRecords::recordKey($otherAuthorsPost),
        );
});

it('truncates long post previews in the resource table', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $content = str_repeat('This post preview should stay concise in the table. ', 5);

    createPost(attributes: ['content' => $content]);

    postsListPageForTesting()
        ->assertSeeText(rtrim(mb_substr($content, 0, 70)))
        ->assertDontSeeText($content);
});

it('denies the Posts index to users with only Users read access', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ListPosts::class)
        ->assertForbidden();
});

it('keeps the Posts resource index read only with a view action for each record', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $tableComponent = Livewire::test(ListPosts::class)->instance();

    throw_unless($tableComponent instanceof HasTable, LogicException::class, 'The Posts index must expose a Filament table.');

    /** @var HasTable&Component $tableComponent */
    $table = $tableComponent->getTable();
    $recordActions = $table->getRecordActions();
    $viewAction = $recordActions[0] ?? null;

    throw_unless($viewAction instanceof ViewAction, LogicException::class, 'The Posts table must expose its view action.');

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

it('filters posts by whether they have comments', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $postWithComments = createPost();
    createCommentOn($postWithComments);
    $postWithoutComments = createPost();

    $testable = postsListPageForTesting()
        ->set('tableFilters.has_comments.value', true);
    $listPosts = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($listPosts, Post::class))
        ->toBe([FilamentTableRecords::recordKey($postWithComments)]);

    $testable->set('tableFilters.has_comments.value', false);
    $withoutComments = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($withoutComments, Post::class))
        ->toBe([FilamentTableRecords::recordKey($postWithoutComments)]);
});

it('filters posts by their creation date range', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $postInsideRange = createPost();
    $postInsideRange->forceFill(['created_at' => '2026-01-15 12:00:00'])->save();
    $postOutsideRange = createPost();
    $postOutsideRange->forceFill(['created_at' => '2026-02-01 12:00:00'])->save();

    $testable = postsListPageForTesting()
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20');
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, Post::class))
        ->toBe([FilamentTableRecords::recordKey($postInsideRange)]);
});

it('combines post date and comment filters and handles an empty result', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $postWithCommentsInsideRange = createPost();
    $postWithCommentsInsideRange->forceFill(['created_at' => '2026-01-15 12:00:00'])->save();
    createCommentOn($postWithCommentsInsideRange);

    $postWithCommentsOutsideRange = createPost();
    $postWithCommentsOutsideRange->forceFill(['created_at' => '2026-02-01 12:00:00'])->save();
    createCommentOn($postWithCommentsOutsideRange);

    $postWithoutCommentsInsideRange = createPost();
    $postWithoutCommentsInsideRange->forceFill(['created_at' => '2026-01-18 12:00:00'])->save();

    $testable = postsListPageForTesting()
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20')
        ->set('tableFilters.has_comments.value', true);
    $listPosts = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($listPosts, Post::class))
        ->toBe([FilamentTableRecords::recordKey($postWithCommentsInsideRange)]);

    $testable
        ->set('tableFilters.created_at.created_from', '2026-03-01')
        ->set('tableFilters.created_at.created_until', '2026-03-05');
    $emptyTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($emptyTable, Post::class))
        ->toBeEmpty();
});

it('shows the image from the posts_images collection', function (): void {
    Storage::fake((string) config('media-library.disk_name'));
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $post = createPost();
    $post->addMedia(UploadedFile::fake()->image('post-image.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $testable = postsListPageForTesting()
        ->assertOk();

    $imageUrl = url($post->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION));

    expect($testable->html())->toContain($imageUrl);
});

it('eager loads post media in one query at the default and larger page sizes', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    foreach (range(1, 25) as $sequence) {
        createPost(attributes: ['content' => 'Post '.$sequence]);
    }

    $mediaQueries = [];

    DB::listen(static function (QueryExecuted $queryExecuted) use (&$mediaQueries): void {
        if (str_contains(strtolower($queryExecuted->sql), 'media')) {
            $mediaQueries[] = $queryExecuted->sql;
        }
    });

    $testable = postsListPageForTesting()
        ->assertOk();
    $defaultPageMediaQueries = $mediaQueries;
    $mediaQueries = [];

    $testable
        ->set('tableRecordsPerPage', 25)
        ->assertOk();
    $largerPageMediaQueries = $mediaQueries;

    expect($defaultPageMediaQueries)
        ->toHaveCount(1)
        ->and($largerPageMediaQueries)->toHaveCount(1);
});

it('shows the fallback for posts without images while keeping them paginated', function (): void {
    $postWithoutImage = createPost(null, ['content' => 'Post without image sentinel']);

    foreach (range(1, 10) as $sequence) {
        createPost(null, ['content' => 'Paginated post '.$sequence]);
    }

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $testable = postsListPageForTesting()
        ->assertOk();
    $table = FilamentTableRecords::table($testable);

    expect($table->getAllTableRecordsCount())->toBe(11);

    $testable->call('setPage', 2);

    $listPosts = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($listPosts, Post::class))
        ->toContain(FilamentTableRecords::recordKey($postWithoutImage))
        ->and($testable->html())
        ->toContain(asset('default-cover.svg'));
});
