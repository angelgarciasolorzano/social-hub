<?php

declare(strict_types=1);

namespace App\Post\Controllers;

use App\Http\Controllers\Controller;
use App\Post\Requests\PostRequest;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PostController extends Controller
{
    public function __invoke(PostRequest $postRequest): RedirectResponse
    {
        $user = $postRequest->user();

        if (! $user) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'No se pudo crear la publicación, por favor intente de nuevo',
            ]);
        }

        $post = $user->posts()->create([
            'content' => $postRequest['content'],
        ]);

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
}
