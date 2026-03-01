<?php

declare(strict_types=1);

namespace App\Comment\Controllers;

use App\Comment\Enums\CommentableType;
use App\Comment\Models\Comment;
use App\Comment\Requests\CommentStoreRequest;
use App\Comment\Resources\CommentCollection;
use App\Http\Controllers\Controller;
use App\Post\Models\Post;
use Illuminate\Database\Eloquent\Model;
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

        /** @var Model & (Post | Comment) $commentable */
        $commentable = $modelType::query()->findOrFail($commentableId);

        $comments = $commentable->comments()
            ->with('user')
            ->latest()
            ->paginate(2);

        return Inertia::flash(['comments' => new CommentCollection($comments)])->back();
    }

    public function store(CommentStoreRequest $request): RedirectResponse
    {
        /** @var string $commentableType */
        $commentableType = $request->input('commentable_type');

        $modelType = Relation::getMorphedModel($commentableType);

        $commentableId = $request['commentable_id'];

        if (! $modelType) {
            return back()->with([
                'type' => 'error',
                'message' => 'El tipo de comentario es invalido.',
            ]);
        }

        /** @var Model & (Post | Comment) $commentable */
        $commentable = $modelType::query()->findOrFail($commentableId);

        $commentable->comments()->create([
            'user_id' => Auth::id(),
            'content' => $request['content'],
        ]);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}
