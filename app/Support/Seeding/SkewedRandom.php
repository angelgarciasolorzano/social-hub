<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use InvalidArgumentException;

final class SkewedRandom
{
    /**
     * Pick an integer between $min and $max (both included), leaning towards
     * $min: a $skew above 1 gives many low values and few high ones, and 1 is
     * uniform. Uses mt_rand, so mt_srand makes the sequence repeatable.
     *
     * @throws InvalidArgumentException
     */
    public static function count(int $min, int $max, float $skew = 2.0): int
    {
        throw_if($min > $max, InvalidArgumentException::class, 'The minimum cannot be greater than the maximum.');

        throw_if($skew <= 0, InvalidArgumentException::class, 'The skew must be greater than zero.');

        $unit = mt_rand() / (mt_getrandmax() + 1);

        return $min + (int) floor(($unit ** $skew) * ($max - $min + 1));
    }
}
