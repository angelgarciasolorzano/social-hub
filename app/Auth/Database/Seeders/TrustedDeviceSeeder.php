<?php

declare(strict_types=1);

namespace App\Auth\Database\Seeders;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

final class TrustedDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            foreach ($this->deviceScenarios() as $deviceScenario) {
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

                if ($deviceScenario['lifecycleAction'] instanceof TrustedDeviceAction) {
                    $this->recordEvent($user, $trustedDevice, $deviceScenario['lifecycleAction']);
                }
            }
        });
    }

    /**
     * @return list<array{
     *     attributes: array{
     *         name: string,
     *         last_used_at: CarbonImmutable|null,
     *         expires_at: CarbonImmutable
     *     },
     *     isRevoked: bool,
     *     lifecycleAction: TrustedDeviceAction|null
     * }>
     */
    private function deviceScenarios(): array
    {
        $now = CarbonImmutable::now();

        return [
            [
                'attributes' => [
                    'name' => 'MacBook personal',
                    'last_used_at' => $now->subHours(2),
                    'expires_at' => $now->addDays(30),
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renamed,
            ],
            [
                'attributes' => [
                    'name' => 'Computadora de trabajo',
                    'last_used_at' => $now->subHours(6),
                    'expires_at' => $now->addDays(30),
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'iPhone personal',
                    'last_used_at' => $now->subDay(),
                    'expires_at' => $now->addDays(30),
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renewed,
            ],
            [
                'attributes' => [
                    'name' => 'Tablet próximo a expirar',
                    'last_used_at' => $now->subDays(2),
                    'expires_at' => $now->addDays(3),
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Renewed,
            ],
            [
                'attributes' => [
                    'name' => 'Dispositivo reactivado',
                    'last_used_at' => null,
                    'expires_at' => $now->addDays(60),
                ],
                'isRevoked' => false,
                'lifecycleAction' => TrustedDeviceAction::Reactivated,
            ],
            [
                'attributes' => [
                    'name' => 'Laptop expirado',
                    'last_used_at' => $now->subDays(14),
                    'expires_at' => $now->subDay(),
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Teléfono expirado',
                    'last_used_at' => $now->subDays(30),
                    'expires_at' => $now->subDays(30),
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Tablet inactiva',
                    'last_used_at' => null,
                    'expires_at' => $now->subDays(7),
                ],
                'isRevoked' => false,
                'lifecycleAction' => null,
            ],
            [
                'attributes' => [
                    'name' => 'Navegador revocado',
                    'last_used_at' => $now->subDays(3),
                    'expires_at' => $now->addDays(30),
                ],
                'isRevoked' => true,
                'lifecycleAction' => TrustedDeviceAction::Revoked,
            ],
            [
                'attributes' => [
                    'name' => 'Laptop de trabajo revocado',
                    'last_used_at' => $now->subDays(30),
                    'expires_at' => $now->addDays(30),
                ],
                'isRevoked' => true,
                'lifecycleAction' => TrustedDeviceAction::Revoked,
            ],
        ];
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
