<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('redirects guests to the login page', function (): void {
    $this->post(route('post.store'), ['content' => 'Contenido de la publicación'])
        ->assertRedirect(route('login'));

    expect(Post::query()->count())->toBe(0);
});

it('requires the content of the post', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->post(route('post.store'), ['content' => ''])
        ->assertSessionHasErrors(['content' => 'El contenido de la publicación es obligatorio']);

    expect(Post::query()->count())->toBe(0);
});

it('rejects content shorter than 10 characters', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->post(route('post.store'), ['content' => 'corto'])
        ->assertSessionHasErrors(['content' => 'El contenido de la publicación no debe ser menor a 10 caracteres']);

    expect(Post::query()->count())->toBe(0);
});

it('creates the post for the authenticated user and flashes a success message', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->post(route('post.store'), ['content' => 'Mi primera publicación']);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');
    $testResponse->assertInertiaFlash('message', 'Publicación creada correctamente');
    $testResponse->assertInertiaFlash('action.label', 'Ver publicación');
    $testResponse->assertInertiaFlash('action.url', route('profile.index'));

    $post = Post::query()->sole();

    expect($post->user_id)->toBe($user->id)
        ->and($post->content)->toBe('Mi primera publicación')
        ->and($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))
        ->toBeEmpty();
});

it('stores the uploaded image in the posts_images collection', function (): void {
    Storage::fake('public');

    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->post(route('post.store'), [
            'content' => 'Publicación con imagen',
            'image_file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');

    $post = Post::query()->sole();

    expect($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))->toHaveCount(1)
        ->and($post->getFirstMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION)?->file_name)->toBe('photo.jpg');
});

it('flashes an error but keeps the post when the image is too big', function (): void {
    Storage::fake('public');
    config(['media-library.max_file_size' => 1024]);

    $user = createUser();

    $this->actingAs($user)
        ->post(route('post.store'), [
            'content' => 'Publicación con imagen enorme',
            'image_file' => UploadedFile::fake()->createWithContent('big.jpg', str_repeat('a', 2048)),
        ])
        ->assertRedirect()
        ->assertSessionHas('notification.type', 'error')
        ->assertSessionHas('notification.message', 'No fue posible subir la imagen, por favor intente de nuevo');

    $post = Post::query()->sole();

    expect($post->user_id)->toBe($user->id)
        ->and($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))
        ->toBeEmpty();
});
