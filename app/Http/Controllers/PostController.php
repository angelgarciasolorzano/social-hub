<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PostController extends Controller
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
     *
     * @return RedirectResponse
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        /** @var  $post Post */
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
        };

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Publicación creada correctamente',
            'action' => [
                'label' => 'Ver publicación',
                'url' => route('profile.show')
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        //
    }
}
