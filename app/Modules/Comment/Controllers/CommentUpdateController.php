<?php

declare(strict_types=1);

namespace App\Modules\Comment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Requests\CommentUpdateRequest;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommentUpdateController extends Controller
{
    #[Authorize('update', 'comment')]
    public function __invoke(CommentUpdateRequest $commentUpdateRequest, Comment $comment): RedirectResponse
    {
        $content = $commentUpdateRequest->string('content')->toString();

        if ($comment->content !== $content) {
            $comment->update(['content' => $content, 'edited_at' => now()]);
        }

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Comentario actualizado correctamente',
        ])->back();
    }
}
