<?php declare(strict_types=1); ?>

@php
    $selectedPermission = $this->selectedPermission();
    $holderRoles = $this->getHolderRoles();
    $holderSummary = $this->getHolderSummary();
    $requirements = $this->getRequirements();
    $hasRisk = $holderSummary['suspended'] > 0 || $holderSummary['withoutMfa'] > 0;
    $metrics = [
        ['label' => __('admin/roles.access.metrics.roles'), 'value' => count($holderRoles), 'icon' => 'heroicon-o-shield-check', 'warning' => null, 'hint' => null],
        ['label' => __('admin/roles.access.metrics.holders'), 'value' => $holderSummary['holders'], 'icon' => 'heroicon-o-users', 'warning' => null, 'hint' => null],
        ['label' => __('admin/roles.access.metrics.without_mfa'), 'value' => $holderSummary['withoutMfa'], 'icon' => 'heroicon-o-shield-exclamation', 'warning' => 'without-mfa', 'hint' => trans_choice('admin/roles.access.warnings.without_mfa', $holderSummary['withoutMfa'], ['count' => $holderSummary['withoutMfa']])],
        ['label' => __('admin/roles.access.metrics.suspended'), 'value' => $holderSummary['suspended'], 'icon' => 'heroicon-o-no-symbol', 'warning' => 'suspended', 'hint' => trans_choice('admin/roles.access.warnings.suspended', $holderSummary['suspended'], ['count' => $holderSummary['suspended']])],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section compact> {{ $this->permissionForm }} </x-filament::section>

    @if ($selectedPermission === null)
        <div
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-white/10"
            data-access-choose
        >
            <span class="bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400 flex size-12 items-center justify-center rounded-full">
                <x-filament::icon icon="heroicon-o-key" class="size-6" aria-hidden="true" />
            </span>
            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin/roles.access.choose') }}</p>
        </div>
    @else
        {{-- The permission card: what it is and whether its holders can use it today. --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
                <span @class([
                    'flex size-12 shrink-0 items-center justify-center rounded-xl',
                    'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400' => $selectedPermission->isSensitive(),
                    'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => ! $selectedPermission->isSensitive(),
                ])>
                    <x-filament::icon
                        :icon="$selectedPermission->isSensitive() ? 'heroicon-o-shield-exclamation' : 'heroicon-o-key'"
                        class="size-6"
                        aria-hidden="true"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ $selectedPermission->label() }}
                        </h2>

                        <x-filament::badge color="gray" size="sm">
                            {{ __('admin/roles.catalog.areas.'.$selectedPermission->area()) }}
                        </x-filament::badge>

                        @if ($selectedPermission->isSensitive())
                            <x-filament::badge
                                color="warning"
                                size="sm"
                                icon="heroicon-m-exclamation-triangle"
                                data-access-sensitive
                            >
                                {{ __('admin/roles.matrix.sensitive') }}
                            </x-filament::badge>
                        @endif
                    </div>

                    <code class="mt-1 block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $selectedPermission->value }}</code>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ $selectedPermission->description() }}
                    </p>

                    @if ($selectedPermission->isSensitive())
                        <p class="text-warning-700 dark:text-warning-400 mt-1 text-sm">
                            {{ __('admin/roles.access.sensitive_description') }}
                        </p>
                    @endif
                </div>
            </div>

            <dl
                class="grid grid-cols-2 gap-px border-t border-gray-950/5 bg-gray-950/5 lg:grid-cols-4 dark:border-white/10 dark:bg-white/10"
                @if ($hasRisk) data-access-warnings @endif
            >
                @foreach ($metrics as $metric)
                    @php($isRisky = $metric['warning'] !== null && $metric['value'] > 0)

                    <div
                        @class([
                            'flex flex-col gap-1 px-6 py-4',
                            'bg-danger-50 dark:bg-danger-500/10' => $isRisky,
                            'bg-white dark:bg-gray-900' => ! $isRisky,
                        ])
                        @if ($isRisky) data-access-warning="{{ $metric['warning'] }}" title="{{ $metric['hint'] }}" @endif
                    >
                        <dt @class([
                            'flex items-center gap-1.5 text-sm font-medium',
                            'text-danger-700 dark:text-danger-400' => $isRisky,
                            'text-gray-500 dark:text-gray-400' => ! $isRisky,
                        ])>
                            <x-filament::icon :icon="$metric['icon']" class="size-4" aria-hidden="true" />
                            {{ $metric['label'] }}
                        </dt>

                        <dd @class([
                            'text-3xl font-semibold tracking-tight',
                            'text-danger-600 dark:text-danger-400' => $isRisky,
                            'text-gray-950 dark:text-white' => ! $isRisky,
                        ])>
                            {{ $metric['value'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if ($hasRisk)
                <p class="bg-danger-50/60 text-danger-700 dark:bg-danger-500/5 dark:text-danger-400 flex items-center gap-2 border-t border-gray-950/5 px-6 py-3 text-sm dark:border-white/10">
                    <x-filament::icon
                        icon="heroicon-m-exclamation-triangle"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ __('admin/roles.access.warnings.heading') }}
                </p>
            @elseif ($holderSummary['holders'] > 0)
                <p class="bg-success-50/60 text-success-700 dark:bg-success-500/5 dark:text-success-400 flex items-center gap-2 border-t border-gray-950/5 px-6 py-3 text-sm dark:border-white/10">
                    <x-filament::icon icon="heroicon-m-check-circle" class="size-4 shrink-0" aria-hidden="true" />
                    {{ __('admin/roles.access.metrics.ready') }}
                </p>
            @endif
        </section>

        {{-- The roles that grant it, as cards with the share of the holders each one reaches. --}}
        <section class="flex flex-col gap-3">
            <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                <x-filament::icon
                    icon="heroicon-o-shield-check"
                    class="size-5 text-gray-400 dark:text-gray-500"
                    aria-hidden="true"
                />
                {{ __('admin/roles.access.roles.heading') }}
            </h3>

            @if ($holderRoles === [])
                <p
                    class="rounded-xl border border-dashed border-gray-300 px-6 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400"
                    data-access-roles-empty
                >
                    {{ __('admin/roles.access.roles.empty') }}
                </p>
            @else
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($holderRoles as $holderRole)
                        <li data-access-role="{{ $holderRole['name'] }}">
                            <{{ $holderRole['url'] !== null ? 'a' : 'div' }}
                                @if ($holderRole['url'] !== null) href="{{ $holderRole['url'] }}" @endif
                                @class([
                                    'group flex h-full flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10',
                                    'hover:ring-primary-500/50 dark:hover:ring-primary-400/50 transition hover:shadow-md' => $holderRole['url'] !== null,
                                ])
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <span @class([
                                        'font-semibold text-gray-950 dark:text-white',
                                        'group-hover:text-primary-600 dark:group-hover:text-primary-400' => $holderRole['url'] !== null,
                                    ])>
                                        {{ $holderRole['label'] }}
                                    </span>

                                    <x-filament::badge :color="$holderRole['isSystem'] ? 'gray' : 'info'" size="sm">
                                        {{ __($holderRole['isSystem'] ? 'admin/roles.types.system' : 'admin/roles.types.custom') }}
                                    </x-filament::badge>
                                </div>

                                <div class="mt-auto flex flex-col gap-1.5">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                                            <x-filament::icon
                                                icon="heroicon-m-users"
                                                class="size-4 text-gray-400 dark:text-gray-500"
                                                aria-hidden="true"
                                            />
                                            {{ trans_choice('admin/roles.access.roles.users_count', $holderRole['usersCount'], ['count' => $holderRole['usersCount']]) }}
                                        </span>

                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ __('admin/roles.access.roles.share', ['share' => $holderRole['share']]) }}
                                        </span>
                                    </div>

                                    <div
                                        class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                                        role="progressbar"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="{{ $holderRole['share'] }}"
                                    >
                                        <div
                                            class="bg-primary-500 h-full rounded-full"
                                            style="width: {{ $holderRole['share'] }}%"
                                        ></div>
                                    </div>
                                </div>
                            </{{ $holderRole['url'] !== null ? 'a' : 'div' }}>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- The chain of related permissions: what it needs, the permission, and what needs it. --}}
        <x-filament::section
            :heading="__('admin/roles.access.requirements.heading')"
            :description="__('admin/roles.access.requirements.description')"
            icon="heroicon-o-link"
            compact
        >
            <div class="flex flex-col items-stretch gap-3 md:flex-row md:items-start">
                @foreach (['requires', 'dependents'] as $relation)
                    @if ($relation === 'dependents')
                        <x-filament::icon
                            icon="heroicon-m-arrow-right"
                            class="size-5 shrink-0 self-center text-gray-400 max-md:rotate-90 md:mt-8 md:self-start dark:text-gray-500"
                            aria-hidden="true"
                        />

                        <span class="bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30 flex items-center justify-center gap-1.5 self-center rounded-lg px-3 py-1.5 text-sm font-semibold ring-1 md:mt-6 md:self-start">
                            <x-filament::icon icon="heroicon-m-key" class="size-4" aria-hidden="true" />
                            {{ $selectedPermission->label() }}
                        </span>

                        <x-filament::icon
                            icon="heroicon-m-arrow-right"
                            class="size-5 shrink-0 self-center text-gray-400 max-md:rotate-90 md:mt-8 md:self-start dark:text-gray-500"
                            aria-hidden="true"
                        />
                    @endif

                    <div
                        @class([
                            'flex flex-1 flex-col gap-2',
                            'md:items-end md:text-right' => $relation === 'requires',
                        ])
                        data-access-requirements="{{ $relation }}"
                    >
                        <span class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                            {{ __('admin/roles.access.requirements.'.$relation) }}
                        </span>

                        <div @class(['flex flex-wrap gap-2', 'md:justify-end' => $relation === 'requires'])>
                            @forelse ($requirements[$relation] as $relatedPermission)
                                @if ($relatedPermission['url'] !== null)
                                    <a
                                        href="{{ $relatedPermission['url'] }}"
                                        x-tooltip="{ content: @js(__('admin/roles.access.link')), theme: $store.theme }"
                                        class="hover:border-primary-500 hover:text-primary-600 dark:hover:border-primary-400 dark:hover:text-primary-400 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 transition dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                                    >
                                        {{ $relatedPermission['label'] }}
                                    </a>
                                @else
                                    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200">
                                        {{ $relatedPermission['label'] }}
                                    </span>
                                @endif
                            @empty
                                <span class="py-1.5 text-sm text-gray-400 italic dark:text-gray-500">
                                    {{ __('admin/roles.access.requirements.none') }}
                                </span>
                            @endforelse
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('admin/roles.access.requirements.'.$relation.'_help') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div data-access-users>{{ $this->table }}</div>
    @endif
</x-filament-panels::page>
