<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function viewAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The detail page of the given announcement.
 *
 * @return Testable<ViewAnnouncement>
 */
function viewAnnouncementPage(Announcement $announcement): Testable
{
    return Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getKey()]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the detail to an administrator without admin.announcements.view', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);

    viewAnnouncementPage(viewAnnouncementFactory()->createOne())->assertForbidden();
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('shows the content, display settings, button and author in the selected locale', function (
        string $locale,
        string $typeLabel,
        string $audienceLabel,
        array $sectionLabels,
        array $fieldLabels,
    ): void {
        Storage::fake('public');
        $user = createUser();
        $announcement = viewAnnouncementFactory()
            ->ofType(AnnouncementType::Critical)
            ->forAudience(AnnouncementAudience::WithoutTwoFactor)
            ->withButton()
            ->notDismissible()
            ->createdBy($user)
            ->createOne(['body' => '<p>Activa la <strong>verificación en dos pasos</strong>.</p>']);
        $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
            ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);

            $testable = viewAnnouncementPage($announcement)
                ->assertOk()
                ->assertSee($announcement->title)
                ->assertSeeHtml('<strong>verificación en dos pasos</strong>')
                ->assertSee($typeLabel)
                ->assertSee($audienceLabel)
                ->assertSee((string) $announcement->button_label)
                ->assertSee((string) $announcement->button_url)
                ->assertSee($user->name)
                ->assertDontSee($locale === 'en' ? 'No image' : 'Sin imagen');

            foreach ($sectionLabels as $sectionLabel) {
                $testable->assertSee($sectionLabel);
            }

            foreach ($fieldLabels as $fieldLabel) {
                $testable->assertSee($fieldLabel);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English' => [
            'en',
            'Critical',
            'Users without two-factor authentication',
            ['Content', 'Display', 'Button', 'History'],
            [
                'Title', 'Message', 'Image', 'Type', 'Audience', 'Shown as', 'Users can dismiss it',
                'Starts at', 'Ends at', 'Button text', 'Button link', 'Created by', 'Created at', 'Updated at',
            ],
        ],
        'Spanish' => [
            'es',
            'Crítico',
            'Usuarios sin autenticación en dos pasos',
            ['Contenido', 'Presentación', 'Botón', 'Historial'],
            [
                'Título', 'Mensaje', 'Imagen', 'Tipo', 'Audiencia', 'Se muestra como',
                'Los usuarios pueden cerrar el anuncio', 'Fecha de inicio', 'Fecha de finalización',
                'Texto del botón', 'Enlace del botón', 'Creado por', 'Fecha de creación', 'Última actualización',
            ],
        ],
    ]);

    it('shows how the announcement is presented in the selected locale', function (
        string $locale,
        string $display,
        string $label,
        string $otherLabel,
        string $fieldLabel,
    ): void {
        $announcement = viewAnnouncementFactory()->createOne(['display' => $display]);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);

            viewAnnouncementPage($announcement)
                ->assertSee($fieldLabel)
                ->assertSee($label)
                ->assertDontSee($otherLabel);
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English toast' => ['en', 'toast', 'Toast', 'Dialog', 'Shown as'],
        'English dialog' => ['en', 'dialog', 'Dialog', 'Toast', 'Shown as'],
        'Spanish toast' => ['es', 'toast', 'Aviso emergente', 'Diálogo', 'Se muestra como'],
        'Spanish dialog' => ['es', 'dialog', 'Diálogo', 'Aviso emergente', 'Se muestra como'],
    ]);

    it('shows translated placeholders for a deleted author, no end date, no button and no image', function (
        string $locale,
        array $placeholders,
    ): void {
        $announcement = viewAnnouncementFactory()->createOne(['created_by' => null, 'ends_at' => null]);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($locale);

            $testable = viewAnnouncementPage($announcement);

            foreach ($placeholders as $placeholder) {
                $testable->assertSee($placeholder);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    })->with([
        'English' => ['en', ['Deleted user', 'No end date', 'No button', 'No image']],
        'Spanish' => ['es', ['Usuario eliminado', 'Sin fecha de finalización', 'Sin botón', 'Sin imagen']],
    ]);

    it('never renders dangerous HTML from the message', function (): void {
        $announcement = viewAnnouncementFactory()->createOne([
            'body' => '<p>Seguro</p><script>alert("script")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">enlace</a>',
        ]);

        viewAnnouncementPage($announcement)
            ->assertSee('Seguro')
            ->assertDontSeeHtml('<script>alert("script")')
            ->assertDontSeeHtml('onerror=')
            ->assertDontSeeHtml('javascript:alert(2)');
    });
});
