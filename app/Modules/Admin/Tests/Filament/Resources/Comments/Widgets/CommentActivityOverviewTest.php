<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\Pages\ViewComment;
use App\Modules\Admin\Filament\Resources\Comments\Widgets\CommentActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Comment\Models\Comment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{replies: Stat, likes: Stat}
 */
function commentActivityStatsFor(Comment $comment): array
{
    $testable = Livewire::test(CommentActivityOverview::class, ['record' => $comment]);
    $component = $testable->instance();

    throw_unless($component instanceof CommentActivityOverview, LogicException::class, 'The comment activity widget must be mounted.');

    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');
    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 2
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat,
        LogicException::class,
        'The comment activity overview must return two stat cards.',
    );

    return [
        'replies' => $stats[0],
        'likes' => $stats[1],
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows counts scoped to the selected comment', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $selectedComment = createCommentOn(createPost());
    $otherComment = createCommentOn(createPost());
    createCommentsOn($selectedComment, 2);
    createCommentsOn($otherComment, 5);
    createLike(likeable: $selectedComment);
    createLike(likeable: $otherComment);
    createLike(likeable: $otherComment);
    createLike(likeable: $otherComment);

    $stats = commentActivityStatsFor($selectedComment);

    expect($stats['replies']->getValue())
        ->toBe(2)
        ->and($stats['likes']->getValue())
        ->toBe(1);
});

it('shows zero when the comment has no replies or likes', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $comment = createCommentOn(createPost());

    $stats = commentActivityStatsFor($comment);

    expect($stats['replies']->getValue())
        ->toBe(0)
        ->and($stats['likes']->getValue())
        ->toBe(0);
});

it('denies the widget to admins without Posts view permission', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin);

    $comment = createCommentOn(createPost());

    Livewire::test(CommentActivityOverview::class, ['record' => $comment])
        ->assertForbidden();
});

it('registers the activity widget on the comment detail page', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);

    $comment = createCommentOn(createPost());
    $component = Livewire::test(ViewComment::class, ['record' => $comment->getKey()])
        ->assertOk()
        ->instance();

    throw_unless($component instanceof ViewComment, LogicException::class, 'The comment detail page must be mounted.');

    $headerWidgetsMethod = new ReflectionMethod(ViewComment::class, 'getHeaderWidgets');

    expect($headerWidgetsMethod->invoke($component))
        ->toBe([CommentActivityOverview::class]);
});
