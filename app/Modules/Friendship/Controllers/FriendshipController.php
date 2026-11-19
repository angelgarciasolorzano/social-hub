<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class FriendshipController extends Controller
{
    public function __construct(private readonly FriendshipService $friendshipService) {}

    #[Authorize('create', [Friendship::class, 'user'])]
    public function store(Request $request, User $user): JsonResponse
    {
        $viewer = $this->authenticatedUser($request);

        return $this->respond($this->friendshipService->send($viewer, $user), $viewer, $user);
    }

    #[Authorize('accept', 'friendship')]
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->accept($friendship, $user), $user, $friendship);
    }

    #[Authorize('decline', 'friendship')]
    public function decline(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->decline($friendship, $user), $user, $friendship);
    }

    #[Authorize('cancel', 'friendship')]
    public function cancel(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->cancel($friendship, $user), $user, $friendship);
    }

    #[Authorize('delete', 'friendship')]
    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->remove($friendship, $user), $user, $friendship);
    }

    /**
     * Get the signed-in user; the routes require authentication, so a missing one is a 401.
     */
    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Answer about the other user of a row, which may already be deleted.
     */
    private function respondFor(FriendshipOperationResult $friendshipOperationResult, User $user, Friendship $friendship): JsonResponse
    {
        return $this->respond($friendshipOperationResult, $user, User::query()->findOrFail($friendship->otherUserId($user)));
    }

    /**
     * Answer with the result and the pair's state and row id read after the action, so the client shows what
     * is stored and can act on the row right away (cancel after sending, remove after accepting, accept an
     * incoming request a refusal revealed). The id is `null` when the pair has no row.
     */
    private function respond(FriendshipOperationResult $friendshipOperationResult, User $viewer, User $otherUser): JsonResponse
    {
        $friendship = $viewer->friendshipWith($otherUser);

        return response()->json([
            'result' => $friendshipOperationResult->value,
            'state' => FriendshipState::forViewer($friendship?->status, $friendship?->requester_id, $viewer->id)->value,
            'friendshipId' => $friendship?->id,
            'message' => $friendshipOperationResult->message(),
        ], $friendshipOperationResult->httpStatus());
    }
}
