<?php declare(strict_types=1); ?>

@php
    $matrixRoles = $this->getMatrixRoles();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="relative overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th
                            scope="col"
                            class="sticky left-0 bg-white px-3 py-2 text-start font-semibold text-gray-950 dark:bg-gray-900 dark:text-white"
                        >
                            {{ __('admin/roles.matrix.permission') }}
                        </th>
                        @foreach ($matrixRoles as $matrixRole)
                            <th
                                scope="col"
                                class="px-2 py-2 text-center align-bottom text-xs font-semibold text-gray-950 dark:text-white"
                                title="{{ $matrixRole['description'] }}"
                            >
                                {{ $matrixRole['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getMatrixAreas() as $areaLabel => $areaPermissions)
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th
                                scope="colgroup"
                                colspan="{{ count($matrixRoles) + 1 }}"
                                class="px-3 py-2 text-start text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                {{ $areaLabel }}
                            </th>
                        </tr>
                        @foreach ($areaPermissions as $matrixPermission)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <th
                                    scope="row"
                                    class="sticky left-0 bg-white px-3 py-2 text-start font-normal text-gray-950 dark:bg-gray-900 dark:text-white"
                                    title="{{ $matrixPermission['description'] }}"
                                >
                                    {{ $matrixPermission['label'] }}
                                    <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $matrixPermission['name'] }}</span>
                                </th>
                                @foreach ($matrixRoles as $matrixRole)
                                    <td class="px-2 py-2 text-center">
                                        @if (in_array($matrixPermission['name'], $matrixRole['permissions'], true))
                                            <x-filament::icon
                                                icon="heroicon-m-check-circle"
                                                class="text-success-600 dark:text-success-400 mx-auto h-5 w-5"
                                                data-matrix-cell="granted"
                                            />
                                            <span class="sr-only">{{ __('admin/roles.matrix.granted') }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                                            <span class="sr-only">{{ __('admin/roles.matrix.not_granted') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
