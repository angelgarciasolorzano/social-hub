<?php

declare(strict_types=1);

use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Resources\AnnouncementBannerResource;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The announcement factory, typed for static analysis.
 */
function bannerAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Serialize the announcement as the application banner receives it, for the given user or a guest.
 *
 * @return array<mixed>
 */
function announcementBannerPayload(Announcement $announcement, ?User $user = null): array
{
    $request = Request::create('/');
    $request->setUserResolver(static fn (): ?User => $user);

    return new AnnouncementBannerResource($announcement)->resolve($request);
}

it('serializes exactly the fields the banner shows', function (): void {
    $announcement = bannerAnnouncementFactory()
        ->ofType(AnnouncementType::Warning)
        ->withButton()
        ->notDismissible()
        ->createOne(['body' => '<p>Mantenimiento a las <strong>22:00</strong>.</p>']);

    expect(announcementBannerPayload($announcement))->toBe([
        'id' => $announcement->id,
        'title' => $announcement->title,
        'bodyHtml' => '<p>Mantenimiento a las <strong>22:00</strong>.</p>',
        'type' => 'warning',
        'buttonLabel' => $announcement->button_label,
        'buttonUrl' => $announcement->button_url,
        'isDismissible' => false,
        'imageUrl' => null,
    ]);
});

it('lets a signed-in user dismiss a dismissible announcement but never a guest', function (): void {
    $announcement = bannerAnnouncementFactory()->createOne();

    expect(announcementBannerPayload($announcement, createUser())['isDismissible'])->toBeTrue()
        ->and(announcementBannerPayload($announcement)['isDismissible'])->toBeFalse();
});

it('never exposes the author, audience or dates', function (): void {
    $payload = announcementBannerPayload(bannerAnnouncementFactory()->createOne());

    expect($payload)->not->toHaveKeys(['createdBy', 'created_by', 'creator', 'audience', 'startsAt', 'endsAt', 'starts_at', 'ends_at']);
});

it('includes the image url when the announcement has an image', function (): void {
    Storage::fake('public');
    $announcement = bannerAnnouncementFactory()->createOne();
    $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

    expect(announcementBannerPayload($announcement->refresh())['imageUrl'])
        ->toBe($announcement->getFirstMediaUrl(Announcement::IMAGE_MEDIA_COLLECTION));
});

it('strips scripts, event handlers and javascript links from the body', function (): void {
    $announcement = bannerAnnouncementFactory()->createOne([
        'body' => '<p>Seguro</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">enlace</a>',
    ]);

    $bodyHtml = announcementBannerPayload($announcement)['bodyHtml'];

    expect($bodyHtml)->toBeString()->toContain('Seguro')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:');
});
