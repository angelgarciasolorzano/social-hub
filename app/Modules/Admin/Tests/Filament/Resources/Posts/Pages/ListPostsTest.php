<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Post\Models\Post;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
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

    $testable = Livewire::test(ListPosts::class)
        ->assertOk()
        ->assertSeeText('Target post 11')
        ->assertSeeText($user->name)
        ->assertSeeText('Other author post sentinel');

    $component = $testable->instance();

    throw_unless($component instanceof HasTable, LogicException::class, 'The Posts index must expose a Filament table.');

    /** @var HasTable&Component $component */
    expect($component->getAllTableRecordsCount())
        ->toBe(12)
        ->and(FilamentTableRecords::visibleRecordKeys($component, Post::class))
        ->not->toContain(FilamentTableRecords::recordKey($firstTargetPost));

    $testable->call('setPage', 2);

    $secondPage = $testable->instance();

    throw_unless($secondPage instanceof HasTable, LogicException::class, 'The Posts second page must expose a Filament table.');

    /** @var HasTable&Component $secondPage */
    expect(FilamentTableRecords::visibleRecordKeys($secondPage, Post::class))
        ->toContain(FilamentTableRecords::recordKey($firstTargetPost));

    $searchTest = Livewire::test(ListPosts::class)
        ->set('tableSearch', 'Target post 01');

    $searchPage = $searchTest->instance();

    throw_unless($searchPage instanceof HasTable, LogicException::class, 'The searched Posts page must expose a Filament table.');

    /** @var HasTable&Component $searchPage */
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

    Livewire::test(ListPosts::class)
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

it('keeps the Posts resource index read only', function (): void {
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

    expect($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('filters posts by whether they have comments', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $postWithComments = createPost();
    createCommentOn($postWithComments);
    $postWithoutComments = createPost();

    $testable = Livewire::test(ListPosts::class)
        ->set('tableFilters.has_comments.value', true);
    $withComments = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($withComments, Post::class))
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

    $testable = Livewire::test(ListPosts::class)
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

    $testable = Livewire::test(ListPosts::class)
        ->set('tableFilters.created_at.created_from', '2026-01-15')
        ->set('tableFilters.created_at.created_until', '2026-01-20')
        ->set('tableFilters.has_comments.value', true);
    $filteredTable = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($filteredTable, Post::class))
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
    $post->clearMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);
    $post->addMedia(UploadedFile::fake()->image('post-image.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $testable = Livewire::test(ListPosts::class)
        ->assertOk();

    $imageUrl = url($post->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION));

    expect($testable->html())->toContain($imageUrl);
});

it('shows the fallback for posts without images while keeping them paginated', function (): void {
    Storage::fake((string) config('media-library.disk_name'));

    $postWithoutImage = createPost(null, ['content' => 'Post without image sentinel']);
    $postWithoutImage->clearMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    foreach (range(1, 10) as $sequence) {
        createPost(null, ['content' => 'Paginated post '.$sequence]);
    }

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $testable = Livewire::test(ListPosts::class)
        ->assertOk();
    $table = FilamentTableRecords::table($testable);

    expect($table->getAllTableRecordsCount())->toBe(11);

    $testable->call('setPage', 2);

    $secondPage = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($secondPage, Post::class))
        ->toContain(FilamentTableRecords::recordKey($postWithoutImage))
        ->and($testable->html())
        ->toContain(asset('default-cover.svg'));
});
