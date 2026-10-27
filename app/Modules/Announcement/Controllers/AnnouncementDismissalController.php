<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

class AnnouncementDismissalController extends Controller
{
    /**
     * Hide the announcement for the authenticated user. Repeating it (a double click or two tabs)
     * changes nothing, so the answer is always the same.
     */
    #[Authorize('dismiss', 'announcement')]
    public function __invoke(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        DB::table('announcement_dismissals')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        return response()->json(['dismissed' => true]);
    }
}
