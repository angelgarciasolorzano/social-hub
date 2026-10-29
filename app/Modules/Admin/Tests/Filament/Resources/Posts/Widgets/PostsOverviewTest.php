<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostsOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Like\Models\Like;
use App\User\Enums\PreferredLocale;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{posts: Stat, comments: Stat, likes: Stat}
 */
function postsOverviewStatsForTesting(PostsOverview $postsOverview): array
{
    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');
    $stats = $statsMethod->invoke($postsOverview);

    throw_unless(
        is_array($stats)
            && count($stats) === 3
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat
            && $stats[2] instanceof Stat,
        LogicException::class,
        'The posts overview must return three stat cards.',
    );

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return [
        'posts' => $stats[0],
        'comments' => $stats[1],
        'likes' => $stats[2],
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows localized post metrics while preserving seven-day charts and colors', function (
    PreferredLocale $preferredLocale,
    string $expectedHeading,
    string $expectedPosts,
    string $expectedPostsDescription,
    string $expectedComments,
    string $expectedCommentsDescription,
    string $expectedLikes,
    string $expectedLikesDescription,
): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    app()->setLocale($preferredLocale->value);

    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminPostsReader);
    $authenticatedUser->update(['preferred_locale' => $preferredLocale->value]);

    $startDate = CarbonImmutable::parse('2026-06-09 00:00:00');
    $post = createPost();
    $post->forceFill(['created_at' => $startDate])->save();

    $lastDayPost = createPost();
    $lastDayPost->forceFill(['created_at' => '2026-06-15 23:59:59'])->save();

    $oldPost = createPost();
    $oldPost->forceFill(['created_at' => '2026-06-08 23:59:59'])->save();

    $commentUser = createUser();
    $commentDates = [
        CarbonImmutable::parse('2026-06-10 10:00:00'),
        CarbonImmutable::parse('2026-06-15 10:00:00'),
        CarbonImmutable::parse('2026-06-08 10:00:00'),
    ];

    foreach ($commentDates as $commentDate) {
        $comment = createCommentOn($post, $commentUser);
        $comment->forceFill(['created_at' => $commentDate])->save();
    }

    $likeDates = [
        CarbonImmutable::parse('2026-06-09 10:00:00'),
        CarbonImmutable::parse('2026-06-13 10:00:00'),
        CarbonImmutable::parse('2026-06-15 10:00:00'),
        CarbonImmutable::parse('2026-06-08 10:00:00'),
    ];

    foreach ($likeDates as $likeDate) {
        $like = new Like;
        $like->forceFill([
            'user_id' => createUser()->getKey(),
            'created_at' => $likeDate,
        ]);
        $post->likes()->save($like);
    }

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(PostsOverview::class)
        ->assertOk()
        ->assertSeeText($expectedHeading)
        ->assertSeeText($expectedPosts)
        ->assertSeeText($expectedPostsDescription)
        ->assertSeeText($expectedComments)
        ->assertSeeText($expectedCommentsDescription)
        ->assertSeeText($expectedLikes)
        ->assertSeeText($expectedLikesDescription);
    $component = $testable->instance();

    throw_unless($component instanceof PostsOverview, LogicException::class, 'The posts overview widget must be mounted.');

    $stats = postsOverviewStatsForTesting($component);

    expect($stats['posts']->getValue())
        ->toBe(3)
        ->and($stats['comments']->getValue())
        ->toBe(3)
        ->and($stats['likes']->getValue())
        ->toBe(4)
        ->and($stats['posts']->getChart())
        ->toBe([1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats['comments']->getChart())
        ->toBe([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats['likes']->getChart())
        ->toBe([1.0, 0.0, 0.0, 0.0, 1.0, 0.0, 1.0])
        ->and($stats['posts']->getColor())
        ->toBe('primary')
        ->and($stats['comments']->getColor())
        ->toBe('info')
        ->and($stats['likes']->getColor())
        ->toBe('warning');
})->with([
    'English' => [
        PreferredLocale::ENGLISH,
        'Posts overview',
        'Posts',
        'New posts per day over the last 7 days',
        'Comments',
        'New comments on posts per day over the last 7 days',
        'Likes',
        'New likes on posts per day over the last 7 days',
    ],
    'Spanish' => [
        PreferredLocale::SPANISH,
        'Resumen de publicaciones',
        'Publicaciones',
        'Nuevas publicaciones por día durante los últimos 7 días',
        'Comentarios',
        'Nuevos comentarios en publicaciones por día durante los últimos 7 días',
        'Me gusta',
        'Nuevos me gusta en publicaciones por día durante los últimos 7 días',
    ],
]);

it('denies the widget to an administrator without post access', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    Livewire::test(PostsOverview::class)->assertForbidden();
});
