<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * @return list<string>
 */
function expectedCommentDetailTexts(PreferredLocale $preferredLocale): array
{
    return match ($preferredLocale) {
        PreferredLocale::ENGLISH => [
            'View Comment',
            'Comment details',
            'Author and publication details for this comment.',
            'Author',
            'Created at',
            'Comment content',
            'The complete message in this conversation.',
            'Message',
            'Selected comment detail sentinel',
        ],
        PreferredLocale::SPANISH => [
            'Ver Comentario',
            'Detalles del comentario',
            'Autor y datos de publicación de este comentario.',
            'Autor',
            'Fecha de creación',
            'Contenido del comentario',
            'El mensaje completo de esta conversación.',
            'Mensaje',
            'Selected comment detail sentinel',
        ],
    };
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('renders the translated comment detail without translating its content', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    $authenticatedUser = createUser();
    $authenticatedUser->update(['preferred_locale' => $preferredLocale->value]);
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $user = createUser();
    $post = createPost();
    $comment = createCommentOn($post, $user, ['content' => 'Selected comment detail sentinel']);

    $this->actingAs($authenticatedUser, 'admin');

    $testResponse = $this->get(CommentResource::getUrl('view', ['record' => $comment]))->assertOk();

    foreach (expectedCommentDetailTexts($preferredLocale) as $expectedText) {
        $testResponse->assertSeeText($expectedText);
    }

    $testResponse->assertSeeText($user->name);
})->with([
    'English' => PreferredLocale::ENGLISH,
    'Spanish' => PreferredLocale::SPANISH,
]);

it('returns singular and plural comment labels in the active language', function (PreferredLocale $preferredLocale, string $expectedModelLabel, string $expectedPluralModelLabel): void {
    app()->setLocale($preferredLocale->value);

    expect(CommentResource::getModelLabel())
        ->toBe($expectedModelLabel)
        ->and(CommentResource::getPluralModelLabel())
        ->toBe($expectedPluralModelLabel);
})->with([
    'English' => [PreferredLocale::ENGLISH, 'comment', 'comments'],
    'Spanish' => [PreferredLocale::SPANISH, 'comentario', 'comentarios'],
]);

it('registers only the hidden direct detail page', function (): void {
    expect(CommentResource::shouldRegisterNavigation())
        ->toBeFalse()
        ->and(array_keys(CommentResource::getPages()))
        ->toBe(['view']);
});

it('denies comment details to users without Posts view permission', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $post = createPost();
    $comment = createCommentOn($post);

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(CommentResource::getUrl('view', ['record' => $comment]))
        ->assertForbidden();
});

it('returns not found for a missing comment record', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminPostsReader->value,
    ]);
    $authenticatedUser->saveAppAuthenticationSecret('filament-test-secret');

    $this->actingAs($authenticatedUser, 'admin');

    $this->get(CommentResource::getUrl('view', ['record' => PHP_INT_MAX]))
        ->assertNotFound();
});

it('denies every comment mutation in the panel even to a posts reader who wrote the comment', function (): void {
    $authenticatedUser = AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminPostsReader,
    );
    $comment = createComment($authenticatedUser);

    expect(Gate::forUser($authenticatedUser)->allows('update', $comment))->toBeTrue()
        ->and(CommentResource::canCreate())->toBeFalse()
        ->and(CommentResource::canEdit($comment))->toBeFalse()
        ->and(CommentResource::canDelete($comment))->toBeFalse()
        ->and(CommentResource::canDeleteAny())->toBeFalse()
        ->and(CommentResource::canRestore($comment))->toBeFalse()
        ->and(CommentResource::canRestoreAny())->toBeFalse()
        ->and(CommentResource::canForceDelete($comment))->toBeFalse()
        ->and(CommentResource::canForceDeleteAny())->toBeFalse();
});
