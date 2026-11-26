<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipListSort;
use App\Modules\Friendship\Requests\FriendshipIndexRequest;
use App\User\Enums\PreferredLocale;
use SanderMuller\FluentValidation\Testing\FluentRulesTester;

it('accepts :dataset as the sort', function (?string $submittedSort): void {
    FluentRulesTester::for(FriendshipIndexRequest::class)
        ->actingAs(createUser())
        ->with(['sort' => $submittedSort])
        ->passes();
})->with([
    'no value' => [null],
    'the most recent' => ['recent'],
    'the name' => ['name'],
]);

it('rejects an unknown sort with its translated message', function (): void {
    $fluentRulesTester = FluentRulesTester::for(FriendshipIndexRequest::class)
        ->actingAs(createUser())
        ->with(['sort' => 'oldest'])
        ->failsOnly('sort');

    expect($fluentRulesTester->errors()->first('sort'))
        ->toBe(translatedTextIn(PreferredLocale::ENGLISH, 'friendship/friends.validation.sort_invalid'));
});

it('sorts by :dataset', function (array $query, FriendshipListSort $friendshipListSort): void {
    expect(FriendshipIndexRequest::create('/friendships', 'GET', $query)->sort())->toBe($friendshipListSort);
})->with([
    'the most recent when the sort is missing' => [[], FriendshipListSort::Recent],
    'the most recent when asked' => [['sort' => 'recent'], FriendshipListSort::Recent],
    'the name when asked' => [['sort' => 'name'], FriendshipListSort::Name],
]);
