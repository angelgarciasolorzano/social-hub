<?php

declare(strict_types=1);

use App\Like\Models\Like;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use LogicException;
use ReflectionMethod;

beforeEach(function (): void {
    AdminFilamentTestContext::configure($this);
});

it('shows scoped comment reply and like counts with seven day charts', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));

    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $post = createPost();
    $otherPost = createPost();
    $user = createUser();
    $startDate = CarbonImmutable::today()->subDays(6);

    $createCommentAt = static function (Post|Comment $commentable, CarbonImmutable $createdAt) use ($user): Comment {
        $comment = createCommentOn($commentable, $user);
        $comment->forceFill(['created_at' => $createdAt])->save();

        return $comment;
    };

    $createLikeAt = static function (Post $post, CarbonImmutable $createdAt) use ($user): void {
        $like = new Like;
        $like->forceFill([
            'user_id' => $user->getKey(),
            'created_at' => $createdAt,
        ]);
        $post->likes()->save($like);
    };

    $firstComment = $createCommentAt($post, $startDate->addDay());
    $secondComment = $createCommentAt($post, $startDate->addDays(6));
    $oldComment = $createCommentAt($post, $startDate->subDays(2));
    $createCommentAt($firstComment, $startDate->addDays(2));
    $createCommentAt($oldComment, $startDate->subDays(2));
    $otherPostComment = $createCommentAt($otherPost, $startDate->addDays(6));
    $createCommentAt($otherPostComment, $startDate->addDays(6));
    $createLikeAt($post, $startDate->addDays(4));
    $createLikeAt($post, $startDate->addDays(6));
    $createLikeAt($post, $startDate->subDays(2));
    $createLikeAt($post, $startDate->subDays(2));
    $createLikeAt($otherPost, $startDate->addDays(6));

    $this->actingAs($authenticatedUser, 'admin');

    $widgetTestable = Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertSeeText('Comments')
        ->assertSeeText('Replies')
        ->assertSeeText('Likes')
        ->assertSeeText('2')
        ->assertSeeText('3')
        ->assertSeeText('4');
    $component = $widgetTestable->instance();

    throw_unless($component instanceof PostActivityOverview, LogicException::class, 'The post activity widget must be mounted.');

    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');
    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 3
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat
            && $stats[2] instanceof Stat,
        LogicException::class,
        'The post activity overview must return three stat cards.',
    );

    expect($stats[0]->getChart())
        ->toBe([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats[1]->getChart())
        ->toBe([0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0])
        ->and($stats[2]->getChart())
        ->toBe([0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 1.0])
        ->and($stats[0]->getValue())
        ->toBe(3)
        ->and($stats[1]->getValue())
        ->toBe(2)
        ->and($stats[2]->getValue())
        ->toBe(4)
        ->and($stats[0]->getChartColor())
        ->toBe('primary')
        ->and($stats[1]->getChartColor())
        ->toBe('info')
        ->and($stats[2]->getChartColor())
        ->toBe('warning');
});

it('forbids access when the admin cannot view posts', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertForbidden();
});

it('hides metrics when the selected post no longer has a valid owner', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $post = createPost();
    $post->forceFill(['user_id' => PHP_INT_MAX])->save();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertDontSeeText('Comments')
        ->assertDontSeeText('Replies')
        ->assertDontSeeText('Likes');
});
