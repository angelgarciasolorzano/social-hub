<?php declare(strict_types=1); ?>

<x-filament-panels::page>
    <x-filament::section heading="Backup destination">
        @if ($this->getBackupListing()->health['healthy'])
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge color="success">Healthy</x-filament::badge>
                <p class="text-sm text-gray-600 dark:text-gray-400">All configured backup health checks passed.</p>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge color="danger">Unhealthy</x-filament::badge>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    One or more configured backup health checks failed.
                </p>
            </div>

            @if ($this->getBackupListing()->health['failed_checks'] !== [])
                <ul class="text-danger-600 dark:text-danger-400 mt-3 list-inside list-disc text-sm">
                    @foreach ($this->getBackupListing()->health['failed_checks'] as $failedCheck)
                        <li>{{ str($failedCheck)->headline() }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
