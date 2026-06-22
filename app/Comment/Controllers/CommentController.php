<?php

declare(strict_types=1);

namespace App\Comment\Controllers;

use App\Comment\Enums\CommentableType;
use App\Comment\Models\Comment;
use App\Comment\Requests\CommentStoreRequest;
use App\Comment\Resources\CommentCollection;
use App\Http\Controllers\Controller;
use App\Post\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentController extends Controller
{
    public function index(CommentableType $commentableType, int $commentableId): RedirectResponse
    {
        /** @var class-string<Post | Comment> $modelType */
        $modelType = $commentableType->modelClass();

        $commentable = $modelType::query()->findOrFail($commentableId);

        $cursorPaginator = $commentable->comments()
            ->with('user')
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return Inertia::flash(['comments' => new CommentCollection($cursorPaginator)])->back();
    }

    public function store(CommentStoreRequest $commentStoreRequest): RedirectResponse
    {
        /** @var string $commentableType */
        $commentableType = $commentStoreRequest->input('commentable_type');

        $modelType = Relation::getMorphedModel($commentableType);

        $commentableId = $commentStoreRequest['commentable_id'];

        if (! $modelType) {
            return back()->with([
                'type' => 'error',
                'message' => 'El tipo de comentario es invalido.',
            ]);
        }

        $commentable = $modelType::query()->findOrFail($commentableId);

        $commentable->comments()->create([
            'user_id' => Auth::id(),
            'content' => $commentStoreRequest['content'],
        ]);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}
