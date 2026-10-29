<?php declare(strict_types=1); ?>

@use('App\Modules\Announcement\Enums\AnnouncementDisplay')
@use('App\Modules\Announcement\Models\Announcement')

@php
    $cardClasses = match ($typeColor) {
        'danger' => 'border-danger-300 bg-danger-50 text-danger-950 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-50',
        'warning' => 'border-warning-300 bg-warning-50 text-warning-950 dark:border-warning-700 dark:bg-warning-950/40 dark:text-warning-50',
        default => 'border-info-300 bg-info-50 text-info-950 dark:border-info-700 dark:bg-info-950/40 dark:text-info-50',
    };

    $imageUrl = $announcement->getFirstMediaUrl(Announcement::IMAGE_MEDIA_COLLECTION);
    $hasButton = $announcement->button_label !== null && $announcement->button_url !== null;
    $isDialog = $announcement->display === AnnouncementDisplay::Dialog;
@endphp

<div class="space-y-3">
    @if ($isDialog)
        <div class="flex justify-center rounded-xl bg-gray-950/50 p-6 dark:bg-gray-950/70">
            <div
                role="dialog"
                aria-label="{{ $announcement->title }}"
                class="w-full max-w-md space-y-3 rounded-xl border p-5 shadow-lg {{ $cardClasses }}"
            >
                <p class="text-base font-semibold">{{ $announcement->title }}</p>

                @if ($imageUrl !== '')
                    <img src="{{ $imageUrl }}" alt="" class="max-h-40 w-full rounded-lg object-cover" />
                @endif

                <div class="prose prose-sm dark:prose-invert max-w-none">{!! $announcement->safeBodyHtml() !!}</div>

                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    @if ($hasButton)
                        <x-filament::button
                            tag="a"
                            :href="$announcement->button_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            size="sm"
                            color="gray"
                        >
                            {{ $announcement->button_label }}
                        </x-filament::button>
                    @endif

                    <x-filament::button size="sm" :color="$typeColor" disabled>
                        {{ __('admin/announcements.preview.acknowledge') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @else
        <div role="status" class="flex gap-4 rounded-xl border p-4 {{ $cardClasses }}">
            @if ($imageUrl !== '')
                <img src="{{ $imageUrl }}" alt="" class="hidden size-16 shrink-0 rounded-lg object-cover sm:block" />
            @endif

            <div class="min-w-0 flex-1 space-y-2">
                <p class="font-semibold">{{ $announcement->title }}</p>

                <div class="prose prose-sm dark:prose-invert max-w-none">{!! $announcement->safeBodyHtml() !!}</div>

                @if ($hasButton)
                    <x-filament::button
                        tag="a"
                        :href="$announcement->button_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        size="sm"
                        :color="$typeColor"
                    >
                        {{ $announcement->button_label }}
                    </x-filament::button>
                @endif
            </div>

            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                :label="__('admin/announcements.preview.close_disabled')"
                disabled
            />
        </div>
    @endif

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
            __('admin/announcements.preview.shown_as', [
                'display' => strtolower($announcement->display->label()),
                'audience' => strtolower($announcement->audience->label()),
            ])
        }} &middot; {{ __($announcement->is_dismissible ? 'admin/announcements.preview.dismissible' : 'admin/announcements.preview.not_dismissible') }} &middot; {{ __('admin/announcements.preview.final_look_note') }}
    </p>
</div>
