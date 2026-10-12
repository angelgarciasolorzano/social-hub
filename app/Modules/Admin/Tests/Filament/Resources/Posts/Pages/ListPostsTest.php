<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Post\Models\Post;
use Filament\Tables\Contracts\HasTable;
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
