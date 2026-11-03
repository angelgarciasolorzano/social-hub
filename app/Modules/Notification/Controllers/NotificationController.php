<?php

declare(strict_types=1);

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Resources\NotificationResource;
use App\Support\Translation\FrontendTranslations;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const int HISTORY_PER_PAGE = 20;

    private const int RECENT_LIMIT = 5;

    public function index(Request $request, FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('notification/center');

        $cursorPaginator = $this->authenticatedUser($request)
            ->notifications()
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(self::HISTORY_PER_PAGE);

        return Inertia::render('notification/Notifications', [
            'notifications' => Inertia::scroll(NotificationResource::collection($cursorPaginator)),
        ]);
    }

    public function recent(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->authenticatedUser($request)
            ->notifications()
            ->latest()
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        return NotificationResource::collection($notifications);
    }

    public function markAsRead(Request $request, string $notificationId): HttpResponse
    {
        $user = $this->authenticatedUser($request);

        $databaseNotification = $user->notifications()->whereKey($notificationId)->firstOrFail();

        $databaseNotification->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request): HttpResponse
    {
        $this->authenticatedUser($request)
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
