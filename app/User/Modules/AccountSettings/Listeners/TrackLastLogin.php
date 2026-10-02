<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Listeners;

use App\User\Models\User;
use Illuminate\Auth\Events\Login;

class TrackLastLogin
{
    public function handle(Login $login): void
    {
        $user = $login->user;

        if (! ($user instanceof User)) {
            return;
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();
    }
}
