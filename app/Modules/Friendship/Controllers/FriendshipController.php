<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\Modules\Friendship\Resources\FriendshipSearchResultResource;
use App\Modules\Friendship\Services\FriendshipSearchService;
use App\Modules\Friendship\Services\FriendshipService;
use App\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * The JSON endpoints of friendships, consumed by React with `useHttp`. Every action answers
 * `{result, state, message}` with the result's HTTP status; the policy runs before any of them.
 */
class FriendshipController extends Controller
{
    public function __construct(
        private readonly FriendshipService $friendshipService,
        private readonly FriendshipSearchService $friendshipSearchService,
    ) {}

    /**
     * Search people by name, one cursor page at a time.
     */
    public function search(FriendshipSearchRequest $friendshipSearchRequest): JsonResponse
    {
        $viewer = $this->authenticatedUser($friendshipSearchRequest);

        $cursorPaginator = $this->friendshipSearchService
            ->search($viewer, $friendshipSearchRequest->string('q')->toString())
            ->withQueryString()
            ->through(fn (User $user): array => new FriendshipSearchResultResource($user)->resolve($friendshipSearchRequest));

        return response()->json($cursorPaginator);
    }

    /**
     * Send a friend request to the user of the route.
     */
    #[Authorize('create', [Friendship::class, 'user'])]
    public function store(Request $request, User $user): JsonResponse
    {
        $viewer = $this->authenticatedUser($request);

        return $this->respond($this->friendshipService->send($viewer, $user), $viewer, $user);
    }

    /**
     * Accept a received friend request.
     */
    #[Authorize('accept', 'friendship')]
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->accept($friendship, $user), $user, $friendship);
    }

    /**
     * Decline a received friend request.
     */
    #[Authorize('decline', 'friendship')]
    public function decline(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->decline($friendship, $user), $user, $friendship);
    }

    /**
     * Cancel a sent friend request.
     */
    #[Authorize('cancel', 'friendship')]
    public function cancel(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->respondFor($this->friendshipService->cancel($friendship, $user), $user, $friendship);
    }

    /**
     * Remove an accepted friendship.
     */
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
     * Answer with the result and the pair's state read after the action, so the client shows what is stored.
     */
    private function respond(FriendshipOperationResult $friendshipOperationResult, User $viewer, User $otherUser): JsonResponse
    {
        $friendship = $viewer->friendshipWith($otherUser);

        return response()->json([
            'result' => $friendshipOperationResult->value,
            'state' => FriendshipState::forViewer($friendship?->status, $friendship?->requester_id, $viewer->id)->value,
            'message' => $friendshipOperationResult->message(),
        ], $friendshipOperationResult->httpStatus());
    }
}
