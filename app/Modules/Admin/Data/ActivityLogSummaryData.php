<?php

declare(strict_types=1);

namespace App\Modules\Admin\Data;

use Spatie\LaravelData\Data;

final class ActivityLogSummaryData extends Data
{
    /**
     * @param  list<string>  $eventLabels
     * @param  list<int>  $eventCounts
     */
    public function __construct(
        public int $totalEvents,
        public array $eventLabels,
        public array $eventCounts,
    ) {}
}
