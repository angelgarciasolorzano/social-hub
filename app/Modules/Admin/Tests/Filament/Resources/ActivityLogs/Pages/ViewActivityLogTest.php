<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use Livewire\Livewire;
use Tests\TestCase;

const ACTIVITY_LOG_DETAIL_SENTINEL_TEXT = 'Texto centinela que el detalle nunca muestra';

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows the stored details and the request context to an activity reader', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);

    $activityLog = ActivityLogFactory::new()
        ->forEvent(ActivityLogAdminEvent::InvitationAccepted)
        ->causedBy($user)
        ->createOne(['properties' => [
            'account_created' => true,
            'roles_assigned' => ['admin', 'admin-posts-reader'],
            'ip' => '203.0.113.7',
            'user_agent' => 'Detail test browser',
        ]]);

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])
        ->assertOk()
        ->assertSeeText('Invitation Accepted')
        ->assertSeeText('account_created')
        ->assertSeeText('Yes')
        ->assertSeeText('admin, admin-posts-reader')
        ->assertSeeText('203.0.113.7')
        ->assertSeeText('Detail test browser');
});

it('marks the request context as not recorded for a console action', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);

    $activityLog = ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->createOne();

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])
        ->assertSeeText('No details recorded')
        ->assertSeeText('Not recorded');
});

it('denies the detail without admin.activity.view', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminPostsReader);

    $activityLog = ActivityLogFactory::new()->createOne();

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])->assertForbidden();
});

it('only registers the list and detail pages', function (): void {
    expect(array_keys(ActivityLogResource::getPages()))->toBe(['index', 'view']);
});

it('never shows the content of the post or comment it refers to', function (): void {
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader, AdminRole::AdminPostsReader);
    $post = createPost($user, ['content' => ACTIVITY_LOG_DETAIL_SENTINEL_TEXT]);
    $comment = createCommentOn($post, $user, ['content' => ACTIVITY_LOG_DETAIL_SENTINEL_TEXT]);

    $postActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::PostCreated)->causedBy($user)->performedOn($post)->createOne();
    $commentActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::CommentCreated)->causedBy($user)->performedOn($comment)->createOne();

    Livewire::test(ViewActivityLog::class, ['record' => $postActivityLog->getKey()])
        ->assertSeeText('Post #'.$post->id)
        ->assertDontSeeText(ACTIVITY_LOG_DETAIL_SENTINEL_TEXT);

    Livewire::test(ViewActivityLog::class, ['record' => $commentActivityLog->getKey()])
        ->assertSeeText('Comment #'.$comment->id)
        ->assertDontSeeText(ACTIVITY_LOG_DETAIL_SENTINEL_TEXT);
});
