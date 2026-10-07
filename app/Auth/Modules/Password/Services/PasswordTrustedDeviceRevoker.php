<?php

declare(strict_types=1);

namespace App\Auth\Modules\Password\Services;

use App\Auth\Modules\Password\Requests\PasswordNewRequest;
use App\Auth\Modules\Password\Requests\PasswordRequest;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\User\Models\User;

final readonly class PasswordTrustedDeviceRevoker
{
    public function __construct(
        private TrustedDeviceService $trustedDeviceService,
    ) {}

    public function revokeAll(User $user, PasswordRequest|PasswordNewRequest $passwordRequest): int
    {
        return $this->trustedDeviceService->revokeAll($user, $passwordRequest);
    }
}
