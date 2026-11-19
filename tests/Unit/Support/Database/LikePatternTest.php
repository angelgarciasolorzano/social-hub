<?php

declare(strict_types=1);

use App\Support\Database\LikePattern;

it('builds a starts-with pattern for :dataset', function (string $term, string $expectedPattern): void {
    expect(LikePattern::startsWith($term))->toBe($expectedPattern);
})->with([
    'a plain term' => ['Ana', 'Ana%'],
    'an empty term' => ['', '%'],
    'the wildcards and a backslash, the backslash escaped first' => ['a%_\\b', 'a\\%\\_\\\\b%'],
    'a term that is already escaped' => ['\\%', '\\\\\\%%'],
]);
