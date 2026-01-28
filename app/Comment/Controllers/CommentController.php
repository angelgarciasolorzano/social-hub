<?php

namespace App\Comment\Controllers;

use App\Comment\Models\Comment;
use App\Comment\Requests\CommentStoreRequest;
use App\Http\Controllers\Controller;
use App\Notifications\CommentNotification;
use App\Post\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CommentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function __invoke(CommentStoreRequest $request)
    {
        $commentableType = Relation::getMorphedModel($request['commentable_type']);
        $commentableId = $request['commentable_id'];

        if (! $commentableType) {
            return back()->with([
                'type' => 'error',
                'message' => 'El tipo de comentario es invalido.'
            ]);
        };

        /** @var Model & (Post | Comment) $commentable */
        $commentable = $commentableType::query()->findOrFail($commentableId);

         $comment = $commentable->comments()->create([
            'user_id' => Auth::id(),
            'content' => $request['content'],
        ]);

        if ($commentable instanceof Post || $commentable instanceof Comment) {
            $commentable->user->notify(new CommentNotification($comment));
        };

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ])->back();
    }
}
