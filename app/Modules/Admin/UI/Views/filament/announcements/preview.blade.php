<?php declare(strict_types=1); ?>

@use('App\Modules\Announcement\Models\Announcement')

@php
    $bannerClasses = match ($typeColor) {
        'danger' => 'border-danger-300 bg-danger-50 text-danger-950 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-50',
        'warning' => 'border-warning-300 bg-warning-50 text-warning-950 dark:border-warning-700 dark:bg-warning-950/40 dark:text-warning-50',
        default => 'border-info-300 bg-info-50 text-info-950 dark:border-info-700 dark:bg-info-950/40 dark:text-info-50',
    };

    $imageUrl = $announcement->getFirstMediaUrl(Announcement::IMAGE_MEDIA_COLLECTION);
@endphp

<div class="space-y-3">
    <div role="status" class="flex gap-4 rounded-xl border p-4 {{ $bannerClasses }}">
        @if ($imageUrl !== '')
            <img src="{{ $imageUrl }}" alt="" class="hidden size-16 shrink-0 rounded-lg object-cover sm:block" />
        @endif

        <div class="min-w-0 flex-1 space-y-2">
            <p class="font-semibold">{{ $announcement->title }}</p>

            <div class="prose prose-sm dark:prose-invert max-w-none">{!! $announcement->safeBodyHtml() !!}</div>

            @if ($announcement->button_label !== null && $announcement->button_url !== null)
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

        @if ($announcement->is_dismissible)
            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                label="Dismiss (disabled in preview)"
                disabled
            />
        @endif
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ $announcement->audience->label() }} &middot; the final look in the application may differ slightly.
    </p>
</div>
