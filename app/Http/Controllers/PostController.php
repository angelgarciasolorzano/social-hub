<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;

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
     */
    public function store(StorePostRequest $request)
    {
        logger($request->file('image_file'));
        $post = Auth::user()->posts()->create([
            'content' => $request['content'],
        ]);

        if ($request->hasFile('image_file')) {
            $post->addMediaFromRequest('image_file')->toMediaCollection('posts_images');
        }

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
