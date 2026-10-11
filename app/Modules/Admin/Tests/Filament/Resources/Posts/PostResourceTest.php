<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Post\Models\Post;
use Filament\Facades\Filament;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Filament::setCurrentPanel('admin');
});

it('lists posts with pagination and content search', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $author = createUser();
    $targetPosts = collect([createPost($author, ['content' => 'Target post 01'])]);

    foreach (range(2, 11) as $sequence) {
        $targetPosts->push(createPost($author, [
            'content' => 'Target post '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]));
    }

    $otherAuthor = createUser();
    $otherAuthorsPost = createPost($otherAuthor, ['content' => 'Other author post sentinel']);
    $firstTargetPost = $targetPosts->first();
    $lastTargetPost = $targetPosts->last();

    throw_unless($firstTargetPost instanceof Post, LogicException::class, 'The first target post must exist.');
    throw_unless($lastTargetPost instanceof Post, LogicException::class, 'The last target post must exist.');

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ListPosts::class)
        ->assertOk()
        ->assertSeeText('Target post 11')
        ->assertSeeText($author->name)
        ->assertSeeText('Other author post sentinel');

    $listPage = $testable->instance();

    throw_unless($listPage instanceof HasTable, LogicException::class, 'The Posts index must expose a Filament table.');

    expect($listPage->getAllTableRecordsCount())
        ->toBe(12)
        ->and(visiblePostResourceIds($listPage))
        ->not->toContain(postResourceKey($firstTargetPost));

    $testable->call('setPage', 2);

    $secondPage = $testable->instance();

    throw_unless($secondPage instanceof HasTable, LogicException::class, 'The Posts second page must expose a Filament table.');

    expect(visiblePostResourceIds($secondPage))
        ->toContain(postResourceKey($firstTargetPost));

    $searchTest = Livewire::test(ListPosts::class)
        ->set('tableSearch', 'Target post 01');

    $searchPage = $searchTest->instance();

    throw_unless($searchPage instanceof HasTable, LogicException::class, 'The searched Posts page must expose a Filament table.');

    expect(visiblePostResourceIds($searchPage))
        ->toContain(postResourceKey($firstTargetPost))
        ->not->toContain(postResourceKey($lastTargetPost), postResourceKey($otherAuthorsPost));
});

it('denies the Posts index without users view any permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ListPosts::class)
        ->assertForbidden();
});

it('keeps the Posts resource index read only', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::UsersReader->value,
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $tableComponent = Livewire::test(ListPosts::class)->instance();

    throw_unless($tableComponent instanceof HasTable, LogicException::class, 'The Posts index must expose a Filament table.');

    $table = $tableComponent->getTable();

    expect(array_keys(PostResource::getPages()))
        ->toBe(['index'])
        ->and($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

/**
 * @return list<string>
 */
function visiblePostResourceIds(HasTable $tableComponent): array
{
    $records = $tableComponent->getTableRecords();

    $pageRecords = match (true) {
        $records instanceof Paginator,
        $records instanceof CursorPaginator => $records->items(),
        $records instanceof Collection => $records->all(),
    };

    return array_values(array_map(static function (mixed $record): string {
        throw_unless($record instanceof Post, LogicException::class, 'The Posts resource must only return Post records.');

        return postResourceKey($record);
    }, $pageRecords));
}

function postResourceKey(Post $post): string
{
    $key = $post->getKey();

    throw_unless(is_int($key) || is_string($key), LogicException::class, 'Post records must use string or integer keys.');

    return (string) $key;
}
