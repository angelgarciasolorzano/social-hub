<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use LogicException;

final class FilamentTableRecords
{
    /**
     * @param  Testable<Component>  $testable
     * @return HasTable&Component
     */
    public static function table(Testable $testable): HasTable
    {
        $component = $testable->instance();

        throw_unless($component instanceof HasTable, LogicException::class, 'The Livewire component must expose a Filament table.');

        /** @var HasTable&Component $component */
        return $component;
    }

    /**
     * @template TRecord of Model
     *
     * @param  class-string<TRecord>  $recordClass
     * @return list<TRecord>
     */
    public static function visibleRecords(HasTable $hasTable, string $recordClass): array
    {
        $records = $hasTable->getTableRecords();

        $pageRecords = match (true) {
            $records instanceof Paginator,
            $records instanceof CursorPaginator => $records->items(),
            $records instanceof Collection => $records->all(),
        };

        return array_values(array_map(static function (mixed $record) use ($recordClass): Model {
            throw_unless(
                $record instanceof $recordClass,
                LogicException::class,
                sprintf('The Filament table must only return [%s] records.', class_basename($recordClass)),
            );

            return $record;
        }, $pageRecords));
    }

    /**
     * @param  class-string<Model>  $recordClass
     * @return list<string>
     */
    public static function visibleRecordKeys(HasTable $hasTable, string $recordClass): array
    {
        return array_map(
            self::recordKey(...),
            self::visibleRecords($hasTable, $recordClass),
        );
    }

    public static function recordKey(Model $model): string
    {
        $key = $model->getKey();

        throw_unless(is_int($key) || is_string($key), LogicException::class, 'Table records must use string or integer keys.');

        return (string) $key;
    }
}
