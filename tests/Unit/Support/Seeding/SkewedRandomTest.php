<?php

declare(strict_types=1);

use App\Support\Seeding\SkewedRandom;

const SKEWED_RANDOM_SEED = 1234;
const SKEWED_RANDOM_DRAWS = 5000;

/**
 * Draw many counts after fixing the seed, so the sample is repeatable.
 *
 * @return list<int>
 */
function drawSkewedCounts(int $min, int $max, float $skew): array
{
    mt_srand(SKEWED_RANDOM_SEED);

    $counts = [];

    foreach (range(1, SKEWED_RANDOM_DRAWS) as $draw) {
        $counts[] = SkewedRandom::count($min, $max, $skew);
    }

    return $counts;
}

it('keeps every count inside the range, both ends included', function (): void {
    $counts = drawSkewedCounts(0, 50, 3.0);

    expect(min($counts))->toBeGreaterThanOrEqual(0)
        ->and(max($counts))->toBeLessThanOrEqual(50);
});

it('reaches the lowest value of the range', function (): void {
    expect(drawSkewedCounts(0, 50, 3.0))->toContain(0);
});

it('produces a variety of values', function (): void {
    expect(count(array_unique(drawSkewedCounts(0, 50, 3.0))))->toBeGreaterThan(10);
});

it('leans towards the minimum when the skew is above one', function (): void {
    $counts = drawSkewedCounts(0, 50, 3.0);

    expect(array_sum($counts) / count($counts))->toBeLessThan(25 - 5);
});

it('is uniform around the midpoint when the skew is one', function (): void {
    $counts = drawSkewedCounts(0, 50, 1.0);

    expect(array_sum($counts) / count($counts))->toBeBetween(23, 27);
});

it('repeats the same sequence with the same seed', function (): void {
    expect(drawSkewedCounts(0, 50, 3.0))->toBe(drawSkewedCounts(0, 50, 3.0));
});

it('returns the only possible value when the minimum equals the maximum', function (): void {
    expect(SkewedRandom::count(7, 7))->toBe(7);
});

it('rejects a minimum greater than the maximum', function (): void {
    expect(fn (): int => SkewedRandom::count(10, 0))->toThrow(InvalidArgumentException::class);
});

it('rejects a skew that is zero or negative', function (float $skew): void {
    expect(fn (): int => SkewedRandom::count(0, 10, $skew))->toThrow(InvalidArgumentException::class);
})->with([
    'zero' => [0.0],
    'negative' => [-1.5],
]);
