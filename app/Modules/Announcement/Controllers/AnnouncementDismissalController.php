<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Requests\AnnouncementDismissalSyncRequest;
use App\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AnnouncementDismissalController extends Controller
{
    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Hide the announcement for the authenticated user. Repeating it (a double click or two tabs)
     * changes nothing, so the answer is always the same.
     */
    #[Authorize('dismiss', 'announcement')]
    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $this->storeDismissals($user, [$announcement->id]);

        return response()->json(['dismissed' => true]);
    }

    /**
     * Store the announcements a guest closed in this browser before signing in. Only the ones the user may still
     * dismiss are kept; the rest may have ended or changed since, so they are skipped without an error.
     */
    public function sync(AnnouncementDismissalSyncRequest $announcementDismissalSyncRequest): JsonResponse
    {
        $user = $this->authenticatedUser($announcementDismissalSyncRequest);

        $dismissedIds = Announcement::query()
            ->whereKey($announcementDismissalSyncRequest->announcementIds())
            ->get()
            ->filter(fn (Announcement $announcement): bool => Gate::forUser($user)->allows('dismiss', $announcement))
            ->map(fn (Announcement $announcement): int => $announcement->id)
            ->values()
            ->all();

        $this->storeDismissals($user, $dismissedIds);

        return response()->json(['dismissed' => $dismissedIds]);
    }

    /**
     * Record the dismissals, ignoring the ones the user already has.
     *
     * @param  array<int, int>  $announcementIds
     */
    private function storeDismissals(User $user, array $announcementIds): void
    {
        DB::table('announcement_dismissals')->insertOrIgnore(array_map(
            fn (int $announcementId): array => [
                'announcement_id' => $announcementId,
                'user_id' => $user->id,
                'created_at' => now(),
            ],
            $announcementIds,
        ));
    }
}
