<?php

declare(strict_types=1);

namespace App\Modules\Comment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentStoreRequest;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentStoreController extends Controller
{
    public function __invoke(CommentStoreRequest $commentStoreRequest): RedirectResponse
    {
        /** @var class-string<Post | Comment> $modelType */
        $modelType = CommentType::from($commentStoreRequest->string('commentable_type')->toString())->modelClass();

        $commentable = $modelType::query()->findOrFail($commentStoreRequest->integer('commentable_id'));

        if ($commentable instanceof Comment && $commentable->commentable_type === CommentType::COMMENT) {
            throw ValidationException::withMessages([
                'commentable_id' => 'No se puede responder a una respuesta.',
            ]);
        }

        $commentable->comments()->create([
            'user_id' => Auth::id(),
            'content' => $commentStoreRequest->string('content')->toString(),
        ]);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}
