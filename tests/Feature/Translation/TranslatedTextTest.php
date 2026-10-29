<?php

declare(strict_types=1);

use App\Support\Translation\TranslatedText;

it('returns the text in the current locale with its placeholders replaced', function (): void {
    app()->setLocale('es');

    expect(TranslatedText::of('admin/common.never'))->toBe('Nunca')
        ->and(TranslatedText::of('admin/users.table.profile_picture_alt', ['name' => 'Ana']))->toBe('Foto de perfil de Ana');

    app()->setLocale('en');

    expect(TranslatedText::of('admin/common.never'))->toBe('Never');
});

it('returns the key of a missing translation, as __() does', function (): void {
    expect(TranslatedText::of('admin/common.does_not_exist'))->toBe('admin/common.does_not_exist');
});

it('rejects a key that names a group of texts', function (): void {
    TranslatedText::of('admin/common.fields');
})->throws(LogicException::class, 'The translation key [admin/common.fields] is a group of texts, not a text.');
