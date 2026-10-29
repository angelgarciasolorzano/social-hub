<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Livewire\Livewire;
use Tests\TestCase;

const ACTIVITY_LOG_DETAIL_SENTINEL_TEXT = 'Texto centinela que el detalle nunca muestra';

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows the stored details and request context in the active language', function (PreferredLocale $preferredLocale, array $expectedTexts): void {
    app()->setLocale($preferredLocale->value);

    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);
    $user->update(['preferred_locale' => $preferredLocale->value]);

    $activityLog = ActivityLogFactory::new()
        ->forEvent(ActivityLogAdminEvent::InvitationAccepted)
        ->causedBy($user)
        ->createOne([
            'created_at' => '2025-03-08 15:04:00',
            'properties' => [
                'account_created' => true,
                'device_active' => false,
                'roles_assigned' => ['admin', 'admin-posts-reader'],
                'ip' => '203.0.113.7',
                'user_agent' => 'Detail test browser',
            ],
        ]);

    $testable = Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])->assertOk();

    foreach ($expectedTexts as $expectedText) {
        $testable->assertSeeText($expectedText);
    }
})->with([
    'English' => [PreferredLocale::ENGLISH, [
        'Activity',
        'What happened, when, who did it, and to what.',
        'Date',
        'Log',
        'Event',
        'Invitation Accepted',
        'Performed by',
        'Subject',
        'Details',
        'The references stored with this activity. Content is never recorded.',
        'account_created',
        'Yes',
        'device_active',
        'No',
        'admin, admin-posts-reader',
        'Request',
        'Where the action came from, when it was made through the web.',
        'IP address',
        'User agent',
        '203.0.113.7',
        'Detail test browser',
        'March 8, 2025',
    ]],
    'Spanish' => [PreferredLocale::SPANISH, [
        'Actividad',
        'Qué ocurrió, cuándo, quién lo hizo y sobre qué.',
        'Fecha',
        'Registro',
        'Evento',
        'Invitación aceptada',
        'Realizado por',
        'Sobre',
        'Detalles',
        'Referencias guardadas con esta actividad. Nunca se registra contenido.',
        'account_created',
        'Sí',
        'device_active',
        'No',
        'admin, admin-posts-reader',
        'Solicitud',
        'Origen de la acción, cuando se realizó desde la web.',
        'Dirección IP',
        'Agente de usuario',
        '203.0.113.7',
        'Detail test browser',
        '8 de marzo de 2025',
    ]],
]);

it('localizes the accessible details label in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);
    $user->update(['preferred_locale' => PreferredLocale::SPANISH->value]);

    $activityLog = ActivityLogFactory::new()
        ->forEvent(ActivityLogAdminEvent::InvitationAccepted)
        ->causedBy($user)
        ->createOne(['properties' => ['guard' => 'admin']]);

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])
        ->assertSeeText('Detalles')
        ->assertDontSeeText('Details');
});

it('shows translated placeholders for an activity without details or request context', function (PreferredLocale $preferredLocale, string $expectedDetails, string $expectedRequest): void {
    app()->setLocale($preferredLocale->value);

    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);
    $user->update(['preferred_locale' => $preferredLocale->value]);

    $activityLog = ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->createOne();

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])
        ->assertSeeText($expectedDetails)
        ->assertSeeText($expectedRequest);
})->with([
    'English' => [PreferredLocale::ENGLISH, 'No details recorded', 'Not recorded'],
    'Spanish' => [PreferredLocale::SPANISH, 'No hay detalles registrados', 'No registrado'],
]);

it('denies the detail without admin.activity.view', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminPostsReader);

    $activityLog = ActivityLogFactory::new()->createOne();

    Livewire::test(ViewActivityLog::class, ['record' => $activityLog->getKey()])->assertForbidden();
});

it('only registers the list and detail pages', function (): void {
    expect(array_keys(ActivityLogResource::getPages()))->toBe(['index', 'view']);
});

it('shows translated resource labels in the active language', function (PreferredLocale $preferredLocale, string $label, string $pluralLabel, string $navigationLabel): void {
    app()->setLocale($preferredLocale->value);

    expect(ActivityLogResource::getModelLabel())->toBe($label)
        ->and(ActivityLogResource::getPluralModelLabel())->toBe($pluralLabel)
        ->and(ActivityLogResource::getNavigationLabel())->toBe($navigationLabel);
})->with([
    'English' => [PreferredLocale::ENGLISH, 'activity', 'activity log', 'Activity log'],
    'Spanish' => [PreferredLocale::SPANISH, 'actividad', 'registro de actividad', 'Registro de actividad'],
]);

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
