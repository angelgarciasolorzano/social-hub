<?php

declare(strict_types=1);

namespace App\Auth\Database\Seeders;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Support\Seeding\SkewedRandom;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use App\User\Modules\TwoFactor\Enums\TwoFactorStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * @phpstan-type DeviceScenario array{
 *     attributes: array{
 *         name: string,
 *         last_used_at: CarbonImmutable|null,
 *         expires_at: CarbonImmutable,
 *         os_name: string,
 *         os_version: string,
 *         is_mobile: bool
 *     },
 *     isRevoked: bool,
 *     lifecycleAction: TrustedDeviceAction|null
 * }
 */
final class TrustedDeviceSeeder extends Seeder
{
    private const int DEFAULT_FORMER_TWO_FACTOR_SHARE_PERCENT = 5;

    private const int MAX_DEVICES_PER_USER = 30;

    private const float DEVICES_SKEW = 2.0;

    public function __construct(private readonly int $formerTwoFactorSharePercent = self::DEFAULT_FORMER_TWO_FACTOR_SHARE_PERCENT) {}

    /**
     * Seed devices that match each user's real two-factor state: confirmed
     * users have a random mix of devices, a share of the users without
     * two-factor authentication are ones who disabled it (all their devices
     * revoked), and nobody else has any.
     */
    public function run(): void
    {
        $users = User::query()->get();
        $formerTwoFactorUserIds = $this->pickFormerTwoFactorUserIds($users);

        foreach ($users as $user) {
            match (TwoFactorStatus::fromUser($user)) {
                TwoFactorStatus::Confirmed => $this->seedConfirmedUserDevices($user),
                TwoFactorStatus::Disabled => \in_array($user->id, $formerTwoFactorUserIds, true)
                    ? $this->seedDisabledTwoFactorDevices($user)
                    : null,
                TwoFactorStatus::Pending => null,
            };
        }
    }

    /**
     * Pick, at random, the share of users without two-factor authentication
     * that had it and disabled it. The test user is never one of them.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>
     */
    private function pickFormerTwoFactorUserIds(Collection $users): array
    {
        $candidateIds = $users
            ->filter(fn (User $user): bool => TwoFactorStatus::fromUser($user) === TwoFactorStatus::Disabled
                && $user->email !== UserSeeder::TEST_USER_EMAIL)
            ->map(fn (User $user): int => $user->id)
            ->shuffle();

        return $candidateIds->take(intdiv($candidateIds->count() * $this->formerTwoFactorSharePercent, 100))->values()->all();
    }

    private function seedConfirmedUserDevices(User $user): void
    {
        $scenarios = collect($this->deviceScenarios())
            ->shuffle()
            ->take(SkewedRandom::count(0, self::MAX_DEVICES_PER_USER, self::DEVICES_SKEW));

        foreach ($scenarios as $scenario) {
            $this->seedDevice($user, $scenario, $scenario['lifecycleAction']);
        }
    }

    /**
     * Seed the revoked devices a user is left with after disabling two-factor
     * authentication, each with the RevokedAll event that action records.
     */
    private function seedDisabledTwoFactorDevices(User $user): void
    {
        $revokedScenarios = collect($this->deviceScenarios())
            ->filter(fn (array $deviceScenario): bool => $deviceScenario['isRevoked'])
            ->shuffle();

        foreach ($revokedScenarios->take(SkewedRandom::count(1, $revokedScenarios->count(), 1.0)) as $deviceScenario) {
            $this->seedDevice($user, $deviceScenario, TrustedDeviceAction::RevokedAll);
        }
    }

    /**
     * @param  DeviceScenario  $deviceScenario
     */
    private function seedDevice(User $user, array $deviceScenario, ?TrustedDeviceAction $trustedDeviceAction): void
    {
        /** @var Factory<TrustedDevice> $trustedDeviceFactory */
        $trustedDeviceFactory = TrustedDevice::factory();

        $trustedDevice = $trustedDeviceFactory->createOne([
            'user_id' => $user->id,
            ...$deviceScenario['attributes'],
        ]);

        $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Created);

        if ($deviceScenario['isRevoked']) {
            $trustedDevice->delete();
        }

        if ($trustedDeviceAction instanceof TrustedDeviceAction) {
            $this->recordEvent($user, $trustedDevice, $trustedDeviceAction);
        }
    }

    /**
     * @return list<DeviceScenario>
     */
    private function deviceScenarios(): array
    {
        $now = CarbonImmutable::now();

        $scenarioTemplates = [
            [
                'attributes' => [
                    'name' => 'MacBook personal',
                    'last_used_at' => $now->subHours(2),
                    'expires_at' => $now->addDays(30),
                    'os_name' => 'Mac',
                    'os_version' => '15',
                    'is_mobile' => false,
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renamed,
            ],
            [
                'attributes' => [
                    'name' => 'Computadora de trabajo',
                    'last_used_at' => $now->subHours(6),
                    'expires_at' => $now->addDays(30),
                    'os_name' => 'Windows',
                    'os_version' => '11',
                    'is_mobile' => false,
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'iPhone personal',
                    'last_used_at' => $now->subDay(),
                    'expires_at' => $now->addDays(30),
                    'os_name' => 'iOS',
                    'os_version' => '18',
                    'is_mobile' => true,
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renewed,
            ],
            [
                'attributes' => [
                    'name' => 'Tablet próximo a expirar',
                    'last_used_at' => $now->subDays(2),
                    'expires_at' => $now->addDays(3),
                    'os_name' => 'Android',
                    'os_version' => '15',
                    'is_mobile' => true,
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renewed,
            ],
            [
                'attributes' => [
                    'name' => 'Dispositivo reactivado',
                    'last_used_at' => null,
                    'expires_at' => $now->addDays(60),
                    'os_name' => 'Linux',
                    'os_version' => '24.04',
                    'is_mobile' => false,
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Reactivated,
            ],
            [
                'attributes' => [
                    'name' => 'Laptop expirado',
                    'last_used_at' => $now->subDays(14),
                    'expires_at' => $now->subDay(),
                    'os_name' => 'Mac',
                    'os_version' => '14',
                    'is_mobile' => false,
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Teléfono expirado',
                    'last_used_at' => $now->subDays(30),
                    'expires_at' => $now->subDays(30),
                    'os_name' => 'Android',
                    'os_version' => '14',
                    'is_mobile' => true,
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Tablet inactiva',
                    'last_used_at' => null,
                    'expires_at' => $now->subDays(7),
                    'os_name' => 'Android',
                    'os_version' => '15',
                    'is_mobile' => true,
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Navegador revocado',
                    'last_used_at' => $now->subDays(3),
                    'expires_at' => $now->addDays(30),
                    'os_name' => 'Linux',
                    'os_version' => '22.04',
                    'is_mobile' => false,
                ],
                'isRevoked' => true,
                'lifecycleAction' => TrustedDeviceAction::Revoked,
            ],
            [
                'attributes' => [
                    'name' => 'Laptop de trabajo revocado',
                    'last_used_at' => $now->subDays(30),
                    'expires_at' => $now->addDays(30),
                    'os_name' => 'Mac',
                    'os_version' => '15',
                    'is_mobile' => false,
                ],
                'isRevoked' => true,
                'lifecycleAction' => TrustedDeviceAction::Revoked,
            ],
        ];

        $deviceScenarios = [];

        foreach ([1, 2, 3] as $copyNumber) {
            foreach ($scenarioTemplates as $scenarioTemplate) {
                $copyOffset = $copyNumber - 1;
                $scenarioTemplate['attributes']['name'] .= $copyNumber > 1 ? " {$copyNumber}" : '';
                $scenarioTemplate['attributes']['last_used_at'] = $scenarioTemplate['attributes']['last_used_at']
                    ?->subDays($copyOffset);
                $scenarioTemplate['attributes']['expires_at'] = $scenarioTemplate['attributes']['expires_at']->isFuture()
                    ? $scenarioTemplate['attributes']['expires_at']->addDays($copyOffset)
                    : $scenarioTemplate['attributes']['expires_at']->subDays($copyOffset);

                $deviceScenarios[] = $scenarioTemplate;
            }
        }

        return $deviceScenarios;
    }

    private function recordEvent(User $user, TrustedDevice $trustedDevice, TrustedDeviceAction $trustedDeviceAction): void
    {
        /** @var Factory<TrustedDeviceEvent> $trustedDeviceEventFactory */
        $trustedDeviceEventFactory = TrustedDeviceEvent::factory();

        $trustedDeviceEventFactory->createOne([
            'user_id' => $user->id,
            'action' => $trustedDeviceAction,
            'device_label' => $trustedDevice->name,
            'device_is_mobile' => $trustedDevice->is_mobile,
            'device_os_name' => $trustedDevice->os_name,
            'ip' => $trustedDevice->ip,
            'user_agent' => $trustedDevice->user_agent,
        ]);
    }
}
