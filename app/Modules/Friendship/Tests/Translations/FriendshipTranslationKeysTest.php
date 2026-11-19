<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\User\Enums\PreferredLocale;

it('has the same keys in Spanish and English', function (string $file): void {
    $englishKeys = array_keys(translationFileTexts('en', $file));
    $spanishKeys = array_keys(translationFileTexts('es', $file));

    expect(array_values(array_diff($englishKeys, $spanishKeys)))
        ->toBe([], "lang/es/{$file}.php is missing keys")
        ->and(array_values(array_diff($spanishKeys, $englishKeys)))
        ->toBe([], "lang/es/{$file}.php has keys that lang/en/{$file}.php does not");
})->with(translationFilesIn('friendship'));

it('has no empty texts and the same placeholders in both languages', function (string $file): void {
    $spanishTexts = translationFileTexts('es', $file);

    foreach (translationFileTexts('en', $file) as $key => $englishText) {
        $spanishText = $spanishTexts[$key] ?? '';

        expect(trim($englishText))->not->toBe('', "lang/en/{$file}.php: '{$key}' is empty")
            ->and(trim($spanishText))->not->toBe('', "lang/es/{$file}.php: '{$key}' is empty")
            ->and(translationPlaceholders($spanishText))
            ->toBe(translationPlaceholders($englishText), "{$file}.{$key}: the placeholders differ between en and es");
    }
})->with(translationFilesIn('friendship'));

it('has the same friendship catalogs in both languages', function (): void {
    $catalogNames = static function (string $locale): array {
        $paths = glob(lang_path("{$locale}/friendship/*.php"));

        return array_map(basename(...), $paths === false ? [] : $paths);
    };

    expect($catalogNames('es'))->toBe($catalogNames('en'));
});

it('has a message for every friendship result in :dataset', function (PreferredLocale $preferredLocale): void {
    foreach (FriendshipOperationResult::cases() as $friendshipOperationResult) {
        expect(translatedTextIn($preferredLocale, $friendshipOperationResult->messageKey()))->not->toBeEmpty();
    }
})->with([
    'English' => [PreferredLocale::ENGLISH],
    'Spanish' => [PreferredLocale::SPANISH],
]);

it('does not reveal a decline or a suspension in the refusal of :dataset', function (FriendshipOperationResult $friendshipOperationResult): void {
    foreach (PreferredLocale::cases() as $preferredLocale) {
        expect(mb_strtolower(translatedTextIn($preferredLocale, $friendshipOperationResult->messageKey())))
            ->not->toContain('rechaz')
            ->not->toContain('declin')
            ->not->toContain('suspend');
    }
})->with([
    'the cooldown' => [FriendshipOperationResult::CooldownActive],
    'an unavailable receiver' => [FriendshipOperationResult::ReceiverUnavailable],
]);

it('answers :dataset with its HTTP status', function (FriendshipOperationResult $friendshipOperationResult, int $httpStatus): void {
    expect($friendshipOperationResult->httpStatus())->toBe($httpStatus);
})->with([
    'sent' => [FriendshipOperationResult::Sent, 200],
    'accepted' => [FriendshipOperationResult::Accepted, 200],
    'declined' => [FriendshipOperationResult::Declined, 200],
    'canceled' => [FriendshipOperationResult::Canceled, 200],
    'removed' => [FriendshipOperationResult::Removed, 200],
    'already pending' => [FriendshipOperationResult::AlreadyPending, 409],
    'already friends' => [FriendshipOperationResult::AlreadyFriends, 409],
    'an incoming request' => [FriendshipOperationResult::IncomingRequestExists, 409],
    'the cooldown' => [FriendshipOperationResult::CooldownActive, 409],
    'the pending limit' => [FriendshipOperationResult::PendingLimitReached, 409],
    'an unavailable receiver' => [FriendshipOperationResult::ReceiverUnavailable, 409],
    'a changed row' => [FriendshipOperationResult::NoLongerPending, 409],
]);
