<?php

declare(strict_types=1);

namespace App\Modules\Comment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Comment\Resources\CommentCollection;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentController extends Controller
{
    public function index(CommentType $commentType, int $commentableId): RedirectResponse
    {
        /** @var class-string<Post | Comment> $modelType */
        $modelType = $commentType->modelClass();

        $commentable = $modelType::query()->findOrFail($commentableId);

        $cursorPaginator = $commentable->comments()
            ->with('user')
            ->withCount('comments')
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::flash(['comments' => new CommentCollection($cursorPaginator)])->back();
    }

    public function store(CommentStoreRequest $commentStoreRequest): RedirectResponse
    {
        /** @var string $commentableType */
        $commentableType = $commentStoreRequest->input('commentable_type');

        /** @var class-string<Post | Comment> $modelType */
        $modelType = CommentType::from($commentableType)->modelClass();

        /** @var int $commentableId */
        $commentableId = $commentStoreRequest->input('commentable_id');

        $commentable = $modelType::query()->findOrFail($commentableId);

        if ($commentable instanceof Comment && $commentable->commentable_type === CommentType::COMMENT) {
            throw ValidationException::withMessages([
                'commentable_id' => 'No se puede responder a una respuesta.',
            ]);
        }

        $commentable->comments()->create([
            'user_id' => Auth::id(),
            'content' => $commentStoreRequest->input('content'),
        ]);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}
