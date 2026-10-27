<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Resources\AnnouncementBannerResource;
use App\User\Models\User;
use App\User\Resources\UserResource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Override;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    #[Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim((string) $message), 'author' => trim((string) $author)],
            'auth' => [
                'user' => $request->user() ? new UserResource($request->user())->resolve($request) : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notification' => fn () => $request->session()->get('notification'),
            'search_results' => fn () => $request->session()->get('search_results'),
            'announcements' => fn (): array => $this->sharedAnnouncements($request),
        ];
    }

    /**
     * The system announcements the visitor should see now, serialized for the banner. The
     * visibility rule lives in Announcement::visibleTo(); this only loads and serializes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sharedAnnouncements(Request $request): array
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->visibleTo($user instanceof User ? $user : null)
            ->with('media')
            ->get();

        return AnnouncementBannerResource::collection($announcements)->resolve($request);
    }
}
