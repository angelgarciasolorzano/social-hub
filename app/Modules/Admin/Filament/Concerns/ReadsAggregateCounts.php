<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Reads the counts of a query that selects aggregates (`count(*)`, `sum(case …)`) into one row.
 */
trait ReadsAggregateCounts
{
    /**
     * The given columns of the aggregate row as numbers; a missing or non-numeric column counts as 0.
     * Takes the Eloquent query or its base query: only the base query runs.
     *
     * @template TModel of Model
     * @template TColumn of string
     *
     * @param  Builder<TModel>|QueryBuilder  $query
     * @param  list<TColumn>  $columns
     * @return array<TColumn, int>
     */
    protected static function aggregateCounts(Builder|QueryBuilder $query, array $columns): array
    {
        $row = ($query instanceof Builder ? $query->toBase() : $query)->first();
        $values = \is_object($row) ? get_object_vars($row) : [];
        $counts = [];

        foreach ($columns as $column) {
            $count = $values[$column] ?? null;
            $counts[$column] = is_numeric($count) ? (int) $count : 0;
        }

        return $counts;
    }
}
