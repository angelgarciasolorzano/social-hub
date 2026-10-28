<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The create page with a valid announcement filled in, with the given fields replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return Testable<CreateAnnouncement>
 */
function filledCreateAnnouncementPage(array $overrides = []): Testable
{
    $state = [
        'title' => 'Mantenimiento programado',
        'body' => '<p>El sitio estará en mantenimiento esta noche.</p>',
        'type' => AnnouncementType::Warning->value,
        'audience' => AnnouncementAudience::Authenticated->value,
        'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'is_dismissible' => true,
        ...$overrides,
    ];

    $testable = Livewire::test(CreateAnnouncement::class);

    foreach ($state as $field => $value) {
        $testable->set('data.'.$field, $value);
    }

    return $testable;
}

/**
 * The form fields that make an announcement invalid in the given way.
 *
 * @return array<string, mixed>
 */
function invalidAnnouncementFormOverrides(string $invalidCase): array
{
    $withButton = ['has_button' => true, 'button_label' => 'Abrir'];

    return match ($invalidCase) {
        'empty title' => ['title' => ''],
        'title too long' => ['title' => str_repeat('a', Announcement::TITLE_MAX_LENGTH + 1)],
        'empty message' => ['body' => ''],
        'end before start' => ['ends_at' => now()->subDay()->format('Y-m-d H:i:s')],
        'button without text' => [...$withButton, 'button_label' => '', 'button_url' => 'https://example.com'],
        'javascript link' => [...$withButton, 'button_url' => 'javascript:alert(1)'],
        'http link' => [...$withButton, 'button_url' => 'http://example.com'],
        'protocol-relative link' => [...$withButton, 'button_url' => '//evil.example.com'],
        default => throw new InvalidArgumentException("Unknown invalid case {$invalidCase}."),
    };
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the create page to an administrator who can only list announcements', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);

    Livewire::test(CreateAnnouncement::class)->assertForbidden();
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('creates an announcement with its image, its author and its audit', function (): void {
        Storage::fake('public');

        filledCreateAnnouncementPage([
            'image' => [UploadedFile::fake()->image('banner.jpg')],
            'has_button' => true,
            'button_label' => 'Ver detalles',
            'button_url' => 'https://example.com/estado',
        ])
            ->call('create')
            ->assertHasNoErrors();

        $announcement = Announcement::query()->sole();

        expect($announcement->title)->toBe('Mantenimiento programado')
            ->and($announcement->type)->toBe(AnnouncementType::Warning)
            ->and($announcement->audience)->toBe(AnnouncementAudience::Authenticated)
            ->and($announcement->button_url)->toBe('https://example.com/estado')
            ->and($announcement->created_by)->toBe(auth('admin')->id())
            ->and($announcement->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->mime_type)->toBe('image/jpeg')
            ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::AnnouncementCreated->value)->count())->toBe(1);
    });

    it('shows the button fields only while has button is on', function (): void {
        Livewire::test(CreateAnnouncement::class)
            ->assertDontSee('Button text')
            ->set('data.has_button', true)
            ->assertSee('Button text')
            ->assertSee('Button link');
    });

    it('accepts a path of this site as the button link', function (): void {
        filledCreateAnnouncementPage([
            'has_button' => true,
            'button_label' => 'Activar 2FA',
            'button_url' => '/setting/two-factor-authentication',
        ])
            ->call('create')
            ->assertHasNoErrors();

        expect(Announcement::query()->sole()->button_url)->toBe('/setting/two-factor-authentication');
    });

    it('rejects an invalid announcement without creating anything', function (string $invalidCase, string $errorField): void {
        filledCreateAnnouncementPage(invalidAnnouncementFormOverrides($invalidCase))
            ->call('create')
            ->assertHasErrors(['data.'.$errorField]);

        expect(Announcement::query()->count())->toBe(0);
    })->with([
        'empty title' => ['empty title', 'title'],
        'title too long' => ['title too long', 'title'],
        'empty message' => ['empty message', 'body'],
        'end before start' => ['end before start', 'ends_at'],
        'button without text' => ['button without text', 'button_label'],
        'javascript link' => ['javascript link', 'button_url'],
        'http link' => ['http link', 'button_url'],
        'protocol-relative link' => ['protocol-relative link', 'button_url'],
    ]);

    it('ignores the button fields once has button is switched off', function (): void {
        filledCreateAnnouncementPage([
            'has_button' => true,
            'button_label' => 'Ver detalles',
            'button_url' => 'javascript:alert(1)',
        ])
            ->set('data.has_button', false)
            ->call('create')
            ->assertHasNoErrors();

        $announcement = Announcement::query()->sole();

        expect($announcement->button_label)->toBeNull()
            ->and($announcement->button_url)->toBeNull();
    });
});
