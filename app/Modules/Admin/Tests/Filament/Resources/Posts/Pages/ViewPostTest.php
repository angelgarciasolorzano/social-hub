<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ListPosts;
use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Posts\Widgets\PostActivityOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Post\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('allows Posts readers to open post details without Users access', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    expect($authenticatedUser->can(AdminPermission::AdminUsersViewAny->value))
        ->toBeFalse()
        ->and($authenticatedUser->can(AdminPermission::AdminUsersView->value))
        ->toBeFalse()
        ->and(array_keys(PostResource::getPages()))
        ->toBe(['index', 'view']);

    $this->get(PostResource::getUrl('view', ['record' => $post]))
        ->assertOk();

    Livewire::test(ListPosts::class)
        ->call('loadTable')
        ->assertSee(PostResource::getUrl('view', ['record' => $post]), false);
});

it('renders translated post detail labels, author and locale dates', function (
    string $locale,
    array $sectionTexts,
    array $fieldLabels,
): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost($user, ['content' => 'Post detail content sentinel']);

    $createdAt = $post->created_at;
    $updatedAt = $post->updated_at;

    throw_unless($createdAt instanceof CarbonImmutable, LogicException::class, 'The post creation timestamp must exist.');
    /** @var CarbonImmutable $createdAt */
    throw_unless($updatedAt instanceof CarbonImmutable, LogicException::class, 'The post update timestamp must exist.');

    /** @var CarbonImmutable $updatedAt */
    $this->actingAs($authenticatedUser, 'admin');

    $originalLocale = app()->getLocale();

    try {
        app()->setLocale($locale);

        $testable = Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertOk()
            ->assertSeeText('Post detail content sentinel')
            ->assertSeeText($user->name)
            ->assertSeeText($createdAt->settings(['locale' => $locale])->translatedFormat('M j, Y'))
            ->assertSeeText($updatedAt->settings(['locale' => $locale])->translatedFormat('M j, Y'));

        foreach ($sectionTexts as $sectionText) {
            $testable->assertSeeText($sectionText);
        }

        foreach ($fieldLabels as $fieldLabel) {
            $testable->assertSeeText($fieldLabel);
        }
    } finally {
        app()->setLocale($originalLocale);
    }
})->with([
    'English' => [
        'en',
        [
            'Post information',
            'Author and history of this post.',
            'Post content',
            'The complete message and image shared in this post.',
        ],
        ['Author', 'Created at', 'Updated at', 'Message', 'Post image'],
    ],
    'Spanish' => [
        'es',
        [
            'Información de la publicación',
            'Autor e historial de esta publicación.',
            'Contenido de la publicación',
            'El mensaje completo y la imagen compartidos en esta publicación.',
        ],
        ['Autor', 'Fecha de creación', 'Última actualización', 'Mensaje', 'Imagen de la publicación'],
    ],
]);

it('shows the image from the posts_images collection on the post detail page', function (): void {
    Storage::fake((string) config('media-library.disk_name'));

    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost($user, ['content' => 'Post image detail content sentinel']);
    $post->addMedia(UploadedFile::fake()->image('post-detail-image.jpg'))
        ->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ViewPost::class, ['record' => $post->getKey()])
        ->assertSeeText('Post image detail content sentinel')
        ->assertSeeText($user->name);

    $imageUrl = url($post->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION));

    expect($testable->html())->toContain($imageUrl);
});

it('shows the fallback image with translated alt text', function (string $locale, string $altText): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    $originalLocale = app()->getLocale();

    try {
        app()->setLocale($locale);

        $testable = Livewire::test(ViewPost::class, ['record' => $post->getKey()])
            ->assertSeeText($post->content);

        expect($testable->html())
            ->toContain(asset('default-cover.svg'))
            ->toContain('alt="'.$altText.'"');
    } finally {
        app()->setLocale($originalLocale);
    }
})->with([
    'English' => ['en', 'Post image'],
    'Spanish' => ['es', 'Imagen de la publicación'],
]);

it('keeps the detail page read-only and includes the activity overview', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ViewPost::class, ['record' => $post->getKey()])
        ->assertOk();

    $component = $testable->instance();

    throw_unless($component instanceof ViewPost, LogicException::class, 'The detail test must resolve the Filament ViewPost page.');

    /** @var ViewPost $component */
    $headerWidgetsMethod = new ReflectionMethod(ViewPost::class, 'getHeaderWidgets');

    expect($component->getCachedHeaderActions())->toBeEmpty()
        ->and($headerWidgetsMethod->invoke($component))
        ->toBe([PostActivityOverview::class]);
});

it('denies the direct post detail route to users with only Users read access', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(PostResource::getUrl('view', ['record' => $post]))
        ->assertForbidden();
});

it('denies post detail when the post has no existing owner', function (): void {
    $authenticatedUser = createUser();

    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);

    $post = createPost();
    $post->forceFill(['user_id' => PHP_INT_MAX])->save();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(ViewPost::class, ['record' => $post->getKey()])
        ->assertForbidden();
});
