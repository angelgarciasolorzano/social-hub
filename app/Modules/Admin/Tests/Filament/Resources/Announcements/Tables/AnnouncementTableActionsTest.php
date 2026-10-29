<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Models\Announcement;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function tableActionAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The announcements list on the given tab with the given row action mounted.
 *
 * @return Testable<ListAnnouncements>
 */
function mountAnnouncementRowAction(string $actionName, Announcement $announcement, string $activeTab = 'all'): Testable
{
    return Livewire::test(ListAnnouncements::class)
        ->set('activeTab', $activeTab)
        ->call('loadTable')
        ->call('mountAction', $actionName, [], [
            'table' => true,
            'recordKey' => FilamentTableRecords::recordKey($announcement),
        ]);
}

/**
 * Whether the given list has an action mounted.
 *
 * @param  Testable<ListAnnouncements>  $testable
 */
function announcementListHasMountedAction(Testable $testable): bool
{
    return $testable->instance()->mountedActions !== [];
}

/**
 * How many audit rows the given admin event has.
 */
function announcementEventCount(ActivityLogAdminEvent $activityLogAdminEvent): int
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('does not offer duplicate or unpublish without the create and update permissions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsView);
    $announcement = tableActionAnnouncementFactory()->active()->createOne();
    $endsAt = $announcement->ends_at?->toDateTimeString();

    $testable = mountAnnouncementRowAction('unpublish', $announcement)->call('callMountedAction');
    $duplicate = mountAnnouncementRowAction('duplicate', $announcement)->call('callMountedAction');

    expect(announcementListHasMountedAction($testable))->toBeFalse()
        ->and(announcementListHasMountedAction($duplicate))->toBeFalse()
        ->and($announcement->refresh()->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and(Announcement::query()->count())->toBe(1)
        ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementUnpublished))->toBe(0);
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('duplicates an announcement as a copy scheduled for tomorrow and opens it', function (): void {
        $this->freezeSecond();
        $announcement = tableActionAnnouncementFactory()->active()->withButton()->createOne();

        $testable = mountAnnouncementRowAction('duplicate', $announcement)->call('callMountedAction');

        $copy = Announcement::query()->whereKeyNot($announcement->id)->sole();

        $testable->assertRedirect(AnnouncementResource::getUrl('edit', ['record' => $copy]));

        expect($copy->title)->toBe($announcement->title)
            ->and($copy->button_url)->toBe($announcement->button_url)
            ->and($copy->starts_at->equalTo(now()->addDay()))->toBeTrue()
            ->and($copy->ends_at)->toBeNull()
            ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementDuplicated))->toBe(1);
    });

    it('unpublishes a running announcement, which moves it to the expired tab', function (): void {
        $announcement = tableActionAnnouncementFactory()->active()->createOne();

        mountAnnouncementRowAction('unpublish', $announcement, 'active')->call('callMountedAction');

        $testable = Livewire::test(ListAnnouncements::class)->set('activeTab', 'expired')->call('loadTable');

        expect($announcement->refresh()->isRunning())->toBeFalse()
            ->and(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Announcement::class))
            ->toBe([FilamentTableRecords::recordKey($announcement)])
            ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementUnpublished))->toBe(1);
    });

    it('shows the announcement table and filter labels in the selected locale', function (string $locale, array $tableLabels): void {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);
            tableActionAnnouncementFactory()->createOne(['created_by' => null, 'ends_at' => null]);

            $testable = Livewire::test(ListAnnouncements::class)->set('activeTab', 'all')->call('loadTable');

            foreach ($tableLabels as $tableLabel) {
                $testable->assertSeeText($tableLabel);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English' => [
            'en',
            [
                'Title', 'Type', 'Audience', 'Shown as', 'Starts', 'Ends', 'Created by', 'No end date', 'Deleted user',
                'Preview', 'Duplicate', 'Unpublish now',
            ],
        ],
        'Spanish' => [
            'es',
            [
                'Título', 'Tipo', 'Audiencia', 'Se muestra como', 'Inicio', 'Finaliza', 'Creado por', 'Sin fecha de finalización', 'Usuario eliminado',
                'Vista previa', 'Duplicar', 'Desactivar ahora',
            ],
        ],
    ]);

    it('shows localized duplicate confirmation and notification copy', function (
        string $locale,
        string $actionLabel,
        string $description,
        string $notificationTitle,
    ): void {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);
            $announcement = tableActionAnnouncementFactory()->active()->createOne();

            $testable = mountAnnouncementRowAction('duplicate', $announcement);
            $testable->__call('assertMountedActionModalSee', [[$actionLabel, $description]]);
            $testable->call('callMountedAction');

            Notification::assertNotified($notificationTitle);
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English' => [
            'en',
            'Duplicate',
            'The copy starts tomorrow and has no end date, so it is not shown before you review it.',
            'Announcement duplicated',
        ],
        'Spanish' => [
            'es',
            'Duplicar',
            'La copia se programa para mañana y no tendrá fecha de finalización, así que no se mostrará hasta que la revises.',
            'Anuncio duplicado',
        ],
    ]);

    it('shows localized unpublish confirmation and notification copy', function (
        string $locale,
        string $actionLabel,
        string $description,
        string $notificationTitle,
    ): void {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);
            $announcement = tableActionAnnouncementFactory()->active()->createOne();

            $testable = mountAnnouncementRowAction('unpublish', $announcement);
            $testable->__call('assertMountedActionModalSee', [[$actionLabel, $description]]);
            $testable->call('callMountedAction');

            Notification::assertNotified($notificationTitle);
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English' => [
            'en',
            'Unpublish now',
            'Users stop seeing this announcement right away.',
            'Announcement unpublished',
        ],
        'Spanish' => [
            'es',
            'Desactivar ahora',
            'Los usuarios dejarán de ver este anuncio de inmediato.',
            'Anuncio desactivado',
        ],
    ]);

    it('does not offer unpublish for an announcement that is not running', function (AnnouncementFactory $announcementFactory): void {
        $announcement = $announcementFactory->createOne();

        $testable = mountAnnouncementRowAction('unpublish', $announcement);

        expect(announcementListHasMountedAction($testable))->toBeFalse();
    })->with([
        'scheduled' => fn (): AnnouncementFactory => tableActionAnnouncementFactory()->scheduled(),
        'expired' => fn (): AnnouncementFactory => tableActionAnnouncementFactory()->expired(),
    ]);

    it('previews the banner with its sanitized message and without dangerous HTML', function (): void {
        $announcement = tableActionAnnouncementFactory()->withButton()->createOne([
            'body' => '<p>Mensaje <strong>importante</strong></p><script>alert("preview")</script><img src="x" onerror="alert(1)">',
        ]);

        $testable = mountAnnouncementRowAction('preview', $announcement);

        expect(announcementListHasMountedAction($testable))->toBeTrue();

        $testable->__call('assertMountedActionModalSee', [[$announcement->title, (string) $announcement->button_label]]);
        $testable->__call('assertMountedActionModalSeeHtml', ['<strong>importante</strong>']);
        $testable->__call('assertMountedActionModalDontSeeHtml', [['<script>alert("preview")', 'onerror=']]);
    });

    it('previews a toast as a floating notice without the dialog button', function (): void {
        $announcement = tableActionAnnouncementFactory()->createOne();

        $testable = mountAnnouncementRowAction('preview', $announcement);

        $testable->__call('assertMountedActionModalSee', [['Shown as toast', $announcement->title]]);
        $testable->__call('assertMountedActionModalDontSee', ['Understood']);
    });

    it('previews a dialog with its close button and without dangerous HTML', function (): void {
        $announcement = tableActionAnnouncementFactory()->asDialog()->withButton()->notDismissible()->createOne([
            'body' => '<p>Aviso <strong>urgente</strong></p><script>alert("dialog")</script>',
        ]);

        $testable = mountAnnouncementRowAction('preview', $announcement);

        $testable->__call('assertMountedActionModalSee', [['Shown as dialog', 'Understood', (string) $announcement->button_label, 'comes back in the next session']]);
        $testable->__call('assertMountedActionModalSeeHtml', ['<strong>urgente</strong>']);
        $testable->__call('assertMountedActionModalDontSeeHtml', ['<script>alert("dialog")']);
    });

    it('localizes static preview copy while preserving announcement content', function (
        string $locale,
        AnnouncementDisplay $announcementDisplay,
        bool $isDismissible,
        string $shownAs,
        string $dismissalBehavior,
        string $presentationLabel,
        string $previewHeading,
        string $closeLabel,
    ): void {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);

            $announcement = tableActionAnnouncementFactory()->createOne([
                'title' => 'Admin-authored announcement title',
                'body' => '<p>Admin-authored message with <strong>formatting</strong>.</p>',
                'audience' => AnnouncementAudience::All,
                'button_label' => 'Admin-authored button label',
                'button_url' => 'https://example.com/details',
                'display' => $announcementDisplay,
                'is_dismissible' => $isDismissible,
            ]);

            $testable = mountAnnouncementRowAction('preview', $announcement);

            $testable->__call('assertMountedActionModalSee', [[
                $announcement->title,
                (string) $announcement->button_label,
                $shownAs,
                $dismissalBehavior,
                $presentationLabel,
                $previewHeading,
                $closeLabel,
                $locale === 'en'
                    ? 'The final appearance in the application may differ slightly.'
                    : 'La apariencia final en la aplicación podría variar ligeramente.',
            ]]);
            $testable->__call('assertMountedActionModalSeeHtml', ['<strong>formatting</strong>']);
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English toast' => [
            'en', AnnouncementDisplay::Toast, true,
            'Shown as toast to everyone', 'remains hidden once closed', 'Close (disabled in preview)', 'Banner preview', 'Close',
        ],
        'English dialog' => [
            'en', AnnouncementDisplay::Dialog, false,
            'Shown as dialog to everyone', 'comes back in the next session after being closed', 'Understood', 'Banner preview', 'Close',
        ],
        'Spanish toast' => [
            'es', AnnouncementDisplay::Toast, false,
            'Se muestra como aviso emergente para todos', 'vuelve a aparecer en la siguiente sesión después de cerrarlo',
            'Cerrar (desactivado en la vista previa)', 'Vista previa del anuncio', 'Cerrar',
        ],
        'Spanish dialog' => [
            'es', AnnouncementDisplay::Dialog, true,
            'Se muestra como diálogo para todos', 'permanece oculto después de cerrarlo', 'Entendido', 'Vista previa del anuncio', 'Cerrar',
        ],
    ]);
});
