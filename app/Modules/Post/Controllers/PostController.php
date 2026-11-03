<?php

declare(strict_types=1);

namespace App\Modules\Post\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Events\PostCreated;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Requests\PostRequest;
use App\Modules\Post\Resources\PostResource;
use App\Support\Translation\FrontendTranslations;
use App\User\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PostController extends Controller
{
    public function store(PostRequest $postRequest): RedirectResponse
    {
        $user = $postRequest->user();

        if ($user === null) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'No se pudo crear la publicación, por favor intente de nuevo',
            ]);
        }

        $post = $user->posts()->create([
            'content' => $postRequest->input('content'),
        ]);

        event(new PostCreated($post));

        if ($postRequest->hasFile('image_file')) {
            try {
                $post->addMediaFromRequest('image_file')->toMediaCollection('posts_images');
            } catch (FileDoesNotExist|FileIsTooBig) {
                return back()->with('notification', [
                    'type' => 'error',
                    'message' => 'No fue posible subir la imagen, por favor intente de nuevo',
                ]);
            }
        }

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Publicación creada correctamente',
            'action' => [
                'label' => 'Ver publicación',
                'url' => route('profile.index'),
            ],
        ])->back();
    }

    public function show(
        Request $request,
        Post $post,
        CommentThreadProps $commentThreadProps,
        FrontendTranslations $frontendTranslations,
    ): Response {
        $viewer = $request->user();

        abort_unless($viewer instanceof User, 401);

        $frontendTranslations->use('notification/center');

        $loadedPost = Post::with(['user.media', 'media'])
            ->withEngagementFor($viewer)
            ->findOrFail($post->id);

        return Inertia::render('post/Show', [
            'post' => new PostResource($loadedPost)->resolve($request),
            $commentThreadProps,
        ]);
    }
}
