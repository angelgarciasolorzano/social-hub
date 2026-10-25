<?php

declare(strict_types=1);

namespace App\User\Database\Seeders;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

/**
 * Suspends a share of the regular accounts and gives another share a past suspension
 * followed by its reactivation. It writes no activity log rows (ActivityLogSeeder
 * mirrors these changes) and never touches administrators or the test user.
 */
class AccountStatusSeeder extends Seeder
{
    private const int DEFAULT_SUSPENDED_SHARE_PERCENT = 3;

    private const int DEFAULT_REACTIVATED_SHARE_PERCENT = 3;

    private const int HISTORY_DAYS = 60;

    private const string ADMIN_GUARD = 'admin';

    private const array SUSPENSION_REASONS = [
        'Publicaciones de spam reiteradas.',
        'Suplantación de identidad reportada por otros usuarios.',
        'Acoso a otros usuarios en los comentarios.',
        'Actividad sospechosa de inicio de sesión desde varios países.',
        'Contenido inapropiado publicado después de varias advertencias.',
    ];

    private const array REACTIVATION_REASONS = [
        'El usuario aclaró la situación con el equipo de soporte.',
        'La revisión no encontró una infracción.',
        'Se cumplió el periodo de suspensión acordado.',
        'El usuario recuperó el control de su cuenta.',
    ];

    public function __construct(
        private readonly int $suspendedSharePercent = self::DEFAULT_SUSPENDED_SHARE_PERCENT,
        private readonly int $reactivatedSharePercent = self::DEFAULT_REACTIVATED_SHARE_PERCENT,
    ) {}

    /**
     * Seed the status history once; a database that already has status changes is left alone.
     */
    public function run(): void
    {
        if (AccountStatusChange::query()->exists()) {
            return;
        }

        /** @var Collection<int, User> $administrators */
        $administrators = User::query()->role(AdminRole::Admin->value, self::ADMIN_GUARD)->get();

        if ($administrators->isEmpty()) {
            return;
        }

        /** @var Collection<int, User> $regularUsers */
        $regularUsers = User::query()
            ->withoutRole(AdminRole::Admin->value, self::ADMIN_GUARD)
            ->where('email', '!=', UserSeeder::TEST_USER_EMAIL)
            ->get()
            ->shuffle();

        $suspendedUsers = $regularUsers->take(intdiv($regularUsers->count() * $this->suspendedSharePercent, 100));

        foreach ($suspendedUsers as $user) {
            $this->suspend($user, $administrators->random());
        }

        $reactivatedUsers = $regularUsers
            ->diff($suspendedUsers)
            ->reject(fn (User $user): bool => $user->trustedDevices()->withTrashed()->exists())
            ->take(intdiv($regularUsers->count() * $this->reactivatedSharePercent, 100));

        foreach ($reactivatedUsers as $reactivatedUser) {
            $this->suspendAndReactivate($reactivatedUser, $administrators->random());
        }
    }

    /**
     * Suspend the account after its last device activity and revoke its active devices
     * at that moment, as AccountStatusService does.
     */
    private function suspend(User $user, User $administrator): void
    {
        /** @var Collection<int, TrustedDevice> $trustedDevices */
        $trustedDevices = $user->trustedDevices()->get();
        $suspendedAt = $this->randomDateBetween($this->lastDeviceActivity($trustedDevices), now());

        $this->createStatusChange($user, $administrator, AccountStatus::Suspended, $suspendedAt);
        $user->forceFill(['status' => AccountStatus::Suspended])->save();

        foreach ($trustedDevices as $trustedDevice) {
            $this->revokeDevice($user, $trustedDevice, $suspendedAt);
        }
    }

    /**
     * Give an account without trusted devices a past suspension followed by its reactivation.
     */
    private function suspendAndReactivate(User $user, User $administrator): void
    {
        $suspendedAt = $this->randomDateBetween(now()->subDays(self::HISTORY_DAYS), now()->subDay());
        $reactivatedAt = $this->randomDateBetween($suspendedAt, now());

        $this->createStatusChange($user, $administrator, AccountStatus::Suspended, $suspendedAt);
        $this->createStatusChange($user, $administrator, AccountStatus::Active, $reactivatedAt);
    }

    /**
     * The latest moment one of the devices was created or used, never earlier than the history window.
     *
     * @param  Collection<int, TrustedDevice>  $trustedDevices
     */
    private function lastDeviceActivity(Collection $trustedDevices): CarbonImmutable
    {
        $lastActivity = now()->subDays(self::HISTORY_DAYS)->toImmutable();

        foreach ($trustedDevices as $trustedDevice) {
            $deviceActivity = $trustedDevice->last_used_at ?? $trustedDevice->created_at;

            if ($deviceActivity->greaterThan($lastActivity)) {
                $lastActivity = $deviceActivity;
            }
        }

        return $lastActivity;
    }

    private function createStatusChange(User $user, User $administrator, AccountStatus $accountStatus, CarbonImmutable $changedAt): void
    {
        /** @var AccountStatusChangeFactory $accountStatusChangeFactory */
        $accountStatusChangeFactory = AccountStatusChange::factory();

        $accountStatusChangeFactory->createOne([
            'user_id' => $user->id,
            'status' => $accountStatus,
            'reason' => fake()->randomElement($accountStatus === AccountStatus::Suspended ? self::SUSPENSION_REASONS : self::REACTIVATION_REASONS),
            'changed_by' => $administrator->id,
            'created_at' => $changedAt,
        ]);
    }

    /**
     * Soft delete the device at the suspension date with the RevokedAll event the real
     * flow records, through the factory so no activity is mirrored.
     */
    private function revokeDevice(User $user, TrustedDevice $trustedDevice, CarbonImmutable $revokedAt): void
    {
        /** @var Factory<TrustedDeviceEvent> $trustedDeviceEventFactory */
        $trustedDeviceEventFactory = TrustedDeviceEvent::factory();

        $trustedDeviceEventFactory->createOne([
            'user_id' => $user->id,
            'action' => TrustedDeviceAction::RevokedAll,
            'device_label' => $trustedDevice->name,
            'device_is_mobile' => $trustedDevice->is_mobile,
            'device_os_name' => $trustedDevice->os_name,
            'ip' => $trustedDevice->ip,
            'user_agent' => $trustedDevice->user_agent,
            'created_at' => $revokedAt,
            'updated_at' => $revokedAt,
        ]);

        $trustedDevice->forceFill(['deleted_at' => $revokedAt])->save();
    }

    private function randomDateBetween(CarbonInterface $from, CarbonInterface $to): CarbonImmutable
    {
        return CarbonImmutable::instance(fake()->dateTimeBetween($from, $to->lessThan($from) ? $from : $to));
    }
}
