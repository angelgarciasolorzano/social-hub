<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use InvalidArgumentException;

final class WeightedRandom
{
    /**
     * Pick one key of $weights with a probability proportional to its weight.
     * Uses mt_rand, so mt_srand makes the sequence repeatable.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int|float>  $weights
     * @return TKey
     *
     * @throws InvalidArgumentException
     */
    public static function pick(array $weights): int|string
    {
        throw_if($weights === [], InvalidArgumentException::class, 'At least one weight is required.');

        foreach ($weights as $weight) {
            throw_if($weight <= 0, InvalidArgumentException::class, 'Every weight must be greater than zero.');
        }

        $threshold = self::unit() * array_sum($weights);
        $accumulated = 0;

        foreach ($weights as $key => $weight) {
            $accumulated += $weight;

            if ($threshold < $accumulated) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * Whether an event with the given probability (0 to 100) happens.
     *
     * @throws InvalidArgumentException
     */
    public static function chance(int $percent): bool
    {
        throw_if($percent < 0 || $percent > 100, InvalidArgumentException::class, 'The percentage must be between 0 and 100.');

        return self::unit() * 100 < $percent;
    }

    /**
     * A random float in the range [0, 1).
     */
    private static function unit(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }
}
