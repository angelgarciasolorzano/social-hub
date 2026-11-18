<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;

final class QueryLog
{
    /**
     * Count the queries `$render` runs, optionally only those `$keepQuery` accepts. Call it once first so
     * caches such as Spatie's permission cache are warm and the measurements compare the render only.
     *
     * @param  Closure(): mixed  $render
     * @param  (Closure(string): bool)|null  $keepQuery
     */
    public static function count(Closure $render, ?Closure $keepQuery = null): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $render();

            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
        }

        return \count($keepQuery instanceof Closure ? array_filter($queries, $keepQuery) : $queries);
    }
}
