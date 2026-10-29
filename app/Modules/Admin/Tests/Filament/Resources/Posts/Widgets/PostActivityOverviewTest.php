<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Enums\PreferredLocale;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{comments: string, commentsDescription: string, replies: string, repliesDescription: string, likes: string, likesDescription: string}
 */
function postActivityOverviewExpectedTextsForTesting(PreferredLocale $preferredLocale): array
{
    return match ($preferredLocale) {
        PreferredLocale::ENGLISH => [
            'comments' => 'Comments',
            'commentsDescription' => 'New comments per day over the last 7 days',
            'replies' => 'Replies',
            'repliesDescription' => 'New replies per day over the last 7 days',
            'likes' => 'Likes',
            'likesDescription' => 'New likes per day over the last 7 days',
        ],
        PreferredLocale::SPANISH => [
            'comments' => 'Comentarios',
            'commentsDescription' => 'Nuevos comentarios por día durante los últimos 7 días',
            'replies' => 'Respuestas',
            'repliesDescription' => 'Nuevas respuestas por día durante los últimos 7 días',
            'likes' => 'Me gusta',
            'likesDescription' => 'Nuevos me gusta por día durante los últimos 7 días',
        ],
    };
}

function createPostActivityOverviewScenarioForTesting(): Post
{
    $post = createPost();
    $otherPost = createPost();
    $user = createUser();
    $startDate = CarbonImmutable::today()->subDays(6);

    $createCommentAt = static function (Post|Comment $commentable, CarbonImmutable $createdAt) use ($user): Comment {
        $comment = createCommentOn($commentable, $user);
        $comment->forceFill(['created_at' => $createdAt])->save();

        return $comment;
    };

    $createLikeAt = static function (Post $likedPost, CarbonImmutable $createdAt): void {
        $like = new Like;
        $like->forceFill([
            'user_id' => createUser()->getKey(),
            'created_at' => $createdAt,
        ]);
        $likedPost->likes()->save($like);
    };

    $firstComment = $createCommentAt($post, $startDate->addDay());
    $createCommentAt($post, $startDate->addDays(6));
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

    return $post;
}

/**
 * @return array{comments: Stat, replies: Stat, likes: Stat}
 */
function postActivityOverviewStatsForTesting(Post $post): array
{
    $widgetTestable = Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertOk();
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

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return [
        'comments' => $stats[0],
        'replies' => $stats[1],
        'likes' => $stats[2],
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('renders activity labels and descriptions in the selected locale', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);
    $authenticatedUser->update(['preferred_locale' => $preferredLocale->value]);

    $post = createPost();
    $expectedTexts = postActivityOverviewExpectedTextsForTesting($preferredLocale);

    Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertOk()
        ->assertSeeText($expectedTexts['comments'])
        ->assertSeeText($expectedTexts['commentsDescription'])
        ->assertSeeText($expectedTexts['replies'])
        ->assertSeeText($expectedTexts['repliesDescription'])
        ->assertSeeText($expectedTexts['likes'])
        ->assertSeeText($expectedTexts['likesDescription']);
})->with([
    'English' => PreferredLocale::ENGLISH,
    'Spanish' => PreferredLocale::SPANISH,
]);

it('preserves scoped activity counts, seven-day charts, and colors in the selected locale', function (PreferredLocale $preferredLocale): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));
    app()->setLocale($preferredLocale->value);

    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);
    $authenticatedUser->update(['preferred_locale' => $preferredLocale->value]);

    $post = createPostActivityOverviewScenarioForTesting();
    $stats = postActivityOverviewStatsForTesting($post);

    expect($stats['comments']->getChart())
        ->toBe([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats['replies']->getChart())
        ->toBe([0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0])
        ->and($stats['likes']->getChart())
        ->toBe([0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 1.0])
        ->and($stats['comments']->getValue())
        ->toBe(3)
        ->and($stats['replies']->getValue())
        ->toBe(2)
        ->and($stats['likes']->getValue())
        ->toBe(4)
        ->and($stats['comments']->getChartColor())
        ->toBe('primary')
        ->and($stats['replies']->getChartColor())
        ->toBe('info')
        ->and($stats['likes']->getChartColor())
        ->toBe('warning');
})->with([
    'English' => PreferredLocale::ENGLISH,
    'Spanish' => PreferredLocale::SPANISH,
]);

it('forbids access when the admin cannot view posts', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin);

    $post = createPost();

    Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertForbidden();
});

it('hides metrics when the selected post no longer has a valid owner', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);
    $post = createPost();
    $post->forceFill(['user_id' => PHP_INT_MAX])->save();

    Livewire::test(PostActivityOverview::class, ['record' => $post])
        ->assertDontSeeText('Comments')
        ->assertDontSeeText('Replies')
        ->assertDontSeeText('Likes');
});
