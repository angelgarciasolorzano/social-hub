<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Mail\AdminInvitationMail;
use App\Modules\Admin\Models\AdminInvitation;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class AdminInvitationService
{
    private const int INVITATION_EXPIRATION_DAYS = 7;

    public function issue(User $invitedByUser, string $email, ?User $targetUser = null): AdminInvitation
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => __('A valid email address is required.'),
            ]);
        }

        $targetUser ??= User::query()->where('email', $normalizedEmail)->first();

        if ($targetUser instanceof User && mb_strtolower(trim($targetUser->email)) !== $normalizedEmail) {
            throw ValidationException::withMessages([
                'email' => __('The selected user must match the invitation email address.'),
            ]);
        }

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addDays(self::INVITATION_EXPIRATION_DAYS);

        $adminInvitation = DB::transaction(function () use (
            $expiresAt,
            $invitedByUser,
            $normalizedEmail,
            $plainToken,
            $targetUser,
        ): AdminInvitation {
            $pendingInvitations = AdminInvitation::query()
                ->where('email', $normalizedEmail)
                ->whereNull('accepted_at')
                ->lockForUpdate()
                ->get();

            $lockedTargetUser = $targetUser instanceof User
                ? User::query()->whereKey($targetUser->getKey())->lockForUpdate()->first()
                : null;

            if ($targetUser instanceof User && ! $lockedTargetUser instanceof User) {
                throw ValidationException::withMessages([
                    'email' => __('The selected user is no longer available.'),
                ]);
            }

            if (
                $lockedTargetUser instanceof User
                && mb_strtolower(trim($lockedTargetUser->email)) !== $normalizedEmail
            ) {
                throw ValidationException::withMessages([
                    'email' => __('The selected user must match the invitation email address.'),
                ]);
            }

            $now = CarbonImmutable::now();

            foreach ($pendingInvitations as $pendingInvitation) {
                $pendingInvitation->expires_at = $now;
                $pendingInvitation->save();
            }

            return AdminInvitation::query()->create([
                'email' => $normalizedEmail,
                'invited_by_user_id' => $invitedByUser->getKey(),
                'target_user_id' => $lockedTargetUser?->getKey(),
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
                'accepted_at' => null,
            ]);
        });

        Mail::to($adminInvitation->email)
            ->send(new AdminInvitationMail($plainToken, $expiresAt));

        return $adminInvitation;
    }

    public function findValidInvitation(string $plainToken): ?AdminInvitation
    {
        return AdminInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', CarbonImmutable::now())
            ->with('targetUser')
            ->first();
    }

    public function accept(string $plainToken, ?string $name = null, ?string $password = null): User
    {
        return DB::transaction(function () use ($name, $password, $plainToken): User {
            $adminInvitation = AdminInvitation::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if (
                ! $adminInvitation instanceof AdminInvitation
                || $adminInvitation->accepted_at !== null
                || ! $adminInvitation->expires_at->isFuture()
            ) {
                $this->rejectInvitation();
            }

            $targetUser = $adminInvitation->target_user_id === null
                ? null
                : User::query()->lockForUpdate()->find($adminInvitation->target_user_id);

            if ($targetUser instanceof User) {
                if (mb_strtolower(trim($targetUser->email)) !== $adminInvitation->email) {
                    $this->rejectInvitation();
                }

                $user = $targetUser;
            } else {
                $existingUser = User::query()
                    ->where('email', $adminInvitation->email)
                    ->lockForUpdate()
                    ->first();

                if ($existingUser instanceof User) {
                    if (! $existingUser->hasVerifiedEmail()) {
                        $this->rejectInvitation();
                    }

                    $user = $existingUser;
                } else {
                    if ($name === null || trim($name) === '') {
                        throw ValidationException::withMessages([
                            'name' => __('A name is required to create your account.'),
                        ]);
                    }

                    if ($password === null || $password === '') {
                        throw ValidationException::withMessages([
                            'password' => __('A password is required to create your account.'),
                        ]);
                    }

                    $user = User::query()->create(['name' => trim($name), 'email' => $adminInvitation->email, 'password' => $password]);
                }
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            if (! $user->hasRole(AdminRole::Admin->value, 'admin')) {
                $user->assignRole(AdminRole::Admin->value);
            }

            $adminInvitation->accepted_at = CarbonImmutable::now();
            $adminInvitation->save();

            return $user;
        });
    }

    private function rejectInvitation(): never
    {
        throw ValidationException::withMessages([
            'invitation' => __('This invitation is invalid, expired, or has already been accepted.'),
        ]);
    }
}
