<?php

namespace App\Post\Controllers;

use App\Http\Controllers\Controller;
use App\Post\Requests\PostRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PostController extends Controller
{
    public function __invoke(PostRequest $request): RedirectResponse
    {
        $post = Auth::user()->posts()->create([
            'content' => $request['content'],
        ]);

        if ($request->hasFile('image_file')) {
            try {
                $post->addMediaFromRequest('image_file')->toMediaCollection('posts_images');
            } catch (FileDoesNotExist | FileIsTooBig $error) {
                return  back()->with('notification', [
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
                'url' => route('profile.index')
            ]
        ])->back();
    }
}