<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use LogicException;
use ReflectionMethod;

final class WidgetStats
{
    /**
     * The stat cards a stats widget shows, in order, after checking it shows exactly `$expectedCount`.
     *
     * @param  class-string<StatsOverviewWidget>  $widgetClass
     * @return list<Stat>
     */
    public static function of(string $widgetClass, int $expectedCount): array
    {
        $component = Livewire::test($widgetClass)->instance();

        throw_unless($component instanceof $widgetClass, LogicException::class, \sprintf('The [%s] widget must be mounted.', class_basename($widgetClass)));

        $stats = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats')->invoke($component);
        $statCards = \is_array($stats) ? array_values(array_filter($stats, static fn (mixed $stat): bool => $stat instanceof Stat)) : [];

        throw_unless(
            \is_array($stats) && \count($stats) === $expectedCount && \count($statCards) === $expectedCount,
            LogicException::class,
            \sprintf('The [%s] widget must return %d stat cards.', class_basename($widgetClass), $expectedCount),
        );

        return $statCards;
    }
}
