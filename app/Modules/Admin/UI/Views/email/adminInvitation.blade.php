<?php declare(strict_types=1); ?>

<x-mail::message>
    # Hello! You were invited to create administrator access for Social Hub.

    <x-mail::button :url="$acceptUrl">Accept invitation</x-mail::button>

    This invitation expires on {{ $expiresAt->toDayDateTimeString() }}. If you were not expecting this message, you can
    ignore it.
</x-mail::message>
