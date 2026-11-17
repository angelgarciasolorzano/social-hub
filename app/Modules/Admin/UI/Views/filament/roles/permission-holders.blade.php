<?php declare(strict_types=1); ?>

@php
    $selectedPermission = $this->selectedPermission();
    $holderRoles = $this->getHolderRoles();
    $holderSummary = $this->getHolderSummary();
    $requirements = $this->getRequirements();
@endphp

<x-filament-panels::page>
    <x-filament::section> {{ $this->permissionForm }} </x-filament::section>

    @if ($selectedPermission === null)
        <x-filament::callout
            color="gray"
            icon="heroicon-o-key"
            :heading="__('admin/roles.access.choose')"
            data-access-choose
        />
    @else
        @if ($selectedPermission->isSensitive())
            <x-filament::callout
                color="warning"
                icon="heroicon-o-shield-exclamation"
                :heading="__('admin/roles.matrix.sensitive')"
                :description="__('admin/roles.access.sensitive_description')"
                data-access-sensitive
            />
        @endif

        @if ($holderSummary['suspended'] > 0 || $holderSummary['withoutMfa'] > 0)
            <x-filament::callout
                color="danger"
                icon="heroicon-o-exclamation-triangle"
                :heading="__('admin/roles.access.warnings.heading')"
                data-access-warnings
            >
                <x-slot name="description">
                    @if ($holderSummary['suspended'] > 0)
                        <span class="block" data-access-warning="suspended">
                            {{ trans_choice('admin/roles.access.warnings.suspended', $holderSummary['suspended'], ['count' => $holderSummary['suspended']]) }}
                        </span>
                    @endif

                    @if ($holderSummary['withoutMfa'] > 0)
                        <span class="block" data-access-warning="without-mfa">
                            {{ trans_choice('admin/roles.access.warnings.without_mfa', $holderSummary['withoutMfa'], ['count' => $holderSummary['withoutMfa']]) }}
                        </span>
                    @endif
                </x-slot>
            </x-filament::callout>
        @endif

        <x-filament::section :heading="__('admin/roles.access.roles.heading')" icon="heroicon-o-shield-check">
            @if ($holderRoles === [])
                <p class="text-sm text-gray-500 dark:text-gray-400" data-access-roles-empty>
                    {{ __('admin/roles.access.roles.empty') }}
                </p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($holderRoles as $holderRole)
                        <li
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                            data-access-role="{{ $holderRole['name'] }}"
                        >
                            @if ($holderRole['url'] !== null)
                                <x-filament::link :href="$holderRole['url']" class="font-medium">
                                    {{ $holderRole['label'] }}
                                </x-filament::link>
                            @else
                                <span class="font-medium text-gray-950 dark:text-white">{{ $holderRole['label'] }}</span>
                            @endif

                            <x-filament::badge :color="$holderRole['isSystem'] ? 'gray' : 'info'" size="sm">
                                {{ __($holderRole['isSystem'] ? 'admin/roles.types.system' : 'admin/roles.types.custom') }}
                            </x-filament::badge>

                            <span class="ms-auto text-sm text-gray-500 dark:text-gray-400">
                                {{ trans_choice('admin/roles.access.roles.users_count', $holderRole['usersCount'], ['count' => $holderRole['usersCount']]) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-link" compact>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach (['requires', 'dependents'] as $relation)
                    <div data-access-requirements="{{ $relation }}">
                        <dt class="font-medium text-gray-950 dark:text-white">
                            {{ __('admin/roles.access.requirements.'.$relation) }}
                        </dt>

                        <dd class="mt-1 flex flex-wrap gap-2">
                            @forelse ($requirements[$relation] as $relatedPermission)
                                @if ($relatedPermission['url'] !== null)
                                    <x-filament::link :href="$relatedPermission['url']" size="sm">
                                        {{ $relatedPermission['label'] }}
                                    </x-filament::link>
                                @else
                                    <span>{{ $relatedPermission['label'] }}</span>
                                @endif
                            @empty
                                <span class="text-gray-500 dark:text-gray-400">{{ __('admin/roles.access.requirements.none') }}</span>
                            @endforelse
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
