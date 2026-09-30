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
     *         expires_at: CarbonImmutable,
     *         os_name: string,
     *         os_version: string,
     *         is_mobile: bool
     *     },
     *     isRevoked: bool,
     *     lifecycleAction: TrustedDeviceAction|null
     * }>
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
