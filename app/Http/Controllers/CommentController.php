<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCommentRequest $request)
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
            'user_id' => auth()->id(),
            'content' => $request['content'],
        ]);

        if ($commentable instanceof Post || $commentable instanceof Comment) {
            $commentable->user->notify(new CommentNotification($comment));
        };

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Comentario publicado correctamente',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Comment $comment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        //
    }
}
