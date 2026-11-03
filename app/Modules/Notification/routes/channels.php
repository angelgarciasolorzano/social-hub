<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'users.{id}.notifications',
    fn (User $user, int $id): bool => $user->id === $id && ! $user->isSuspended(),
);
