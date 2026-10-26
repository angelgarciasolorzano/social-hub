<?php declare(strict_types=1); ?>

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Quick access</x-slot>

        <x-slot name="description">Open an area you have permission to access.</x-slot>

        <nav aria-label="Dashboard quick links">
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($this->getQuickLinks() as $quickLink)
                    <li>
                        <a
                            href="{{ $quickLink['url'] }}"
                            class="group hover:border-primary-300 hover:bg-primary-50/50 focus-visible:ring-primary-600 dark:hover:border-primary-700 flex h-full min-h-24 items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800/80"
                        >
                            <span class="bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-300 flex size-11 shrink-0 items-center justify-center rounded-xl">
                                <x-filament::icon :icon="$quickLink['icon']" class="size-5" aria-hidden="true" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $quickLink['label'] }}
                                </span>

                                <span class="mt-1 block text-sm leading-5 text-gray-500 dark:text-gray-400">
                                    {{ $quickLink['description'] }}
                                </span>
                            </span>

                            <x-filament::icon
                                icon="heroicon-o-arrow-up-right"
                                class="group-hover:text-primary-600 size-4 shrink-0 text-gray-400 transition-colors dark:text-gray-500"
                                aria-hidden="true"
                            />
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </x-filament::section>
</x-filament-widgets::widget>
