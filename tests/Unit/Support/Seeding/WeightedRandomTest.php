<?php

declare(strict_types=1);

use App\Support\Seeding\WeightedRandom;

const WEIGHTED_RANDOM_SEED = 4321;
const WEIGHTED_RANDOM_DRAWS = 10000;

/**
 * Pick many times after fixing the seed, so the sample is repeatable.
 *
 * @param  array<string, int>  $weights
 * @return list<string>
 */
function drawWeightedKeys(array $weights): array
{
    mt_srand(WEIGHTED_RANDOM_SEED);

    $keys = [];

    foreach (range(1, WEIGHTED_RANDOM_DRAWS) as $draw) {
        $keys[] = WeightedRandom::pick($weights);
    }

    return $keys;
}

/**
 * Share (0 to 1) of draws, after fixing the seed, in which the event happened.
 */
function chanceShare(int $percent): float
{
    mt_srand(WEIGHTED_RANDOM_SEED);

    $hits = 0;

    foreach (range(1, WEIGHTED_RANDOM_DRAWS) as $draw) {
        $hits += WeightedRandom::chance($percent) ? 1 : 0;
    }

    return $hits / WEIGHTED_RANDOM_DRAWS;
}

it('picks keys in proportion to their weights', function (): void {
    $keys = drawWeightedKeys(['a' => 70, 'b' => 30]);

    $shareOfA = count(array_filter($keys, fn (string $key): bool => $key === 'a')) / count($keys);

    expect($shareOfA)->toBeBetween(0.66, 0.74);
});

it('only returns keys that were given', function (): void {
    $keys = drawWeightedKeys(['a' => 1, 'b' => 2, 'c' => 3]);

    expect(array_diff($keys, ['a', 'b', 'c']))->toBeEmpty();
});

it('can return every key', function (): void {
    expect(array_unique(drawWeightedKeys(['a' => 1, 'b' => 2, 'c' => 3])))->toHaveCount(3);
});

it('accepts decimal weights', function (): void {
    expect(WeightedRandom::pick(['a' => 0.25, 'b' => 0.75]))->toBeIn(['a', 'b']);
});

it('returns the only key when there is just one', function (): void {
    expect(WeightedRandom::pick(['only' => 5]))->toBe('only');
});

it('repeats the same sequence with the same seed', function (): void {
    expect(drawWeightedKeys(['a' => 70, 'b' => 30]))->toBe(drawWeightedKeys(['a' => 70, 'b' => 30]));
});

it('rejects an empty list of weights', function (): void {
    expect(fn (): string|int => WeightedRandom::pick([]))->toThrow(InvalidArgumentException::class);
});

it('rejects a weight that is zero or negative', function (int $weight): void {
    expect(fn (): string|int => WeightedRandom::pick(['a' => 10, 'b' => $weight]))->toThrow(InvalidArgumentException::class);
})->with([
    'zero' => [0],
    'negative' => [-3],
]);

it('never happens with a chance of 0 percent', function (): void {
    expect(chanceShare(0))->toBe(0.0);
});

it('always happens with a chance of 100 percent', function (): void {
    expect(chanceShare(100))->toBe(1.0);
});

it('happens in proportion to its percentage', function (): void {
    expect(chanceShare(30))->toBeBetween(0.27, 0.33);
});

it('rejects a percentage outside 0 to 100', function (int $percent): void {
    expect(fn (): bool => WeightedRandom::chance($percent))->toThrow(InvalidArgumentException::class);
})->with([
    'below zero' => [-1],
    'above one hundred' => [101],
]);
