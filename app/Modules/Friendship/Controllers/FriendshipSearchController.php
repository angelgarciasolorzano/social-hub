<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\Modules\Friendship\Resources\FriendshipSearchResultResource;
use App\Modules\Friendship\Services\FriendshipSearchService;
use App\Support\Translation\FrontendTranslations;
use App\User\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the people search page. It is a page of its own, apart from the JSON actions of
 * `FriendshipController`, like `TrustedDeviceIndexController`; the friends page (SOC-84) is the index.
 */
final class FriendshipSearchController extends Controller
{
    public function __construct(private readonly FriendshipSearchService $friendshipSearchService) {}

    /**
     * Render the page: the term lives in the URL and the results scroll by cursor.
     */
    public function __invoke(FriendshipSearchRequest $friendshipSearchRequest, FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('friendship/search', 'notification/center');

        $viewer = $friendshipSearchRequest->user();

        abort_unless($viewer instanceof User, 401);

        $term = $friendshipSearchRequest->string('q')->trim()->toString();

        return Inertia::render('friendship/Search', [
            'filters' => ['q' => $term],
            'searchTermMinLength' => FriendshipSearchRequest::TERM_MIN_LENGTH,
            'searchResults' => Inertia::scroll(fn (): AnonymousResourceCollection => FriendshipSearchResultResource::collection(
                $this->friendshipSearchService->search($viewer, $term),
            )),
        ]);
    }
}
