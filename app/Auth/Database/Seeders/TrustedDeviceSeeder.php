<?php

declare(strict_types=1);

namespace App\Auth\Database\Seeders;

use App\Auth\Database\Factories\TrustedDeviceFactory;
use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Support\Seeding\SkewedRandom;
use App\Support\Seeding\WeightedRandom;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use App\User\Modules\TwoFactor\Enums\TwoFactorStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use LogicException;

final class TrustedDeviceSeeder extends Seeder
{
    private const int DEFAULT_FORMER_TWO_FACTOR_SHARE_PERCENT = 5;

    private const int MAX_DEVICES_PER_USER = 30;

    private const float DEVICES_SKEW = 2.0;

    private const int MAX_REVOKED_DEVICES_AFTER_DISABLING_TWO_FACTOR = 6;

    /** @var array<string, int> */
    private const array STATE_WEIGHTS = ['active' => 45, 'expiringSoon' => 10, 'expired' => 25, 'revoked' => 20];

    /** @var array<string, int> */
    private const array ACTIVE_HISTORY_WEIGHTS = ['none' => 55, 'renamed' => 20, 'renewed' => 15, 'reactivated' => 10];

    /** @var array<string, int> */
    private const array PASSIVE_HISTORY_WEIGHTS = ['none' => 75, 'renamed' => 25];

    public function __construct(private readonly int $formerTwoFactorSharePercent = self::DEFAULT_FORMER_TWO_FACTOR_SHARE_PERCENT) {}

    /**
     * Seed devices that match each user's real two-factor state: confirmed
     * users have a random mix of devices in random states, a share of the
     * users without two-factor authentication are ones who disabled it (all
     * their devices revoked), and nobody else has any.
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
        Collection::times(
            SkewedRandom::count(0, self::MAX_DEVICES_PER_USER, self::DEVICES_SKEW),
            fn (): bool => $this->seedDevice($user, WeightedRandom::pick(self::STATE_WEIGHTS), TrustedDeviceAction::Revoked),
        );
    }

    /**
     * Seed the revoked devices a user is left with after disabling two-factor
     * authentication, each with the RevokedAll event that action records.
     */
    private function seedDisabledTwoFactorDevices(User $user): void
    {
        Collection::times(
            SkewedRandom::count(1, self::MAX_REVOKED_DEVICES_AFTER_DISABLING_TWO_FACTOR, 1.0),
            fn (): bool => $this->seedDevice($user, 'revoked', TrustedDeviceAction::RevokedAll),
        );
    }

    /**
     * Create one device in the given state with the events that explain it,
     * each dated after the previous one.
     */
    private function seedDevice(User $user, string $state, TrustedDeviceAction $trustedDeviceAction): bool
    {
        $trustedDevice = $this->deviceFactoryFor($state)->createOne(['user_id' => $user->id]);

        $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Created, $trustedDevice->created_at);

        if ($state === 'revoked') {
            $this->recordEvent($user, $trustedDevice, $trustedDeviceAction, $trustedDevice->deleted_at ?? $trustedDevice->created_at);
        } elseif ($state === 'active') {
            $this->seedActiveDeviceHistory($user, $trustedDevice);
        } elseif (WeightedRandom::pick(self::PASSIVE_HISTORY_WEIGHTS) === 'renamed') {
            $lastDay = $trustedDevice->expires_at->isFuture() ? CarbonImmutable::now() : $trustedDevice->expires_at;

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renamed, $this->randomDateBetween($trustedDevice->created_at, $lastDay));
        }

        return true;
    }

    private function seedActiveDeviceHistory(User $user, TrustedDevice $trustedDevice): void
    {
        $now = CarbonImmutable::now();
        $history = WeightedRandom::pick(self::ACTIVE_HISTORY_WEIGHTS);

        if ($history === 'renamed') {
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renamed, $this->randomDateBetween($trustedDevice->created_at, $now));
        } elseif ($history === 'renewed') {
            $renewedAt = $this->randomDateBetween($trustedDevice->created_at, $now);

            $this->extendTrust($trustedDevice, $renewedAt, $trustedDevice->last_used_at ?? $renewedAt);
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renewed, $renewedAt);
        } elseif ($history === 'reactivated') {
            $revokedAt = $this->randomDateBetween($trustedDevice->created_at, $now);
            $reactivatedAt = $this->randomDateBetween($revokedAt, $now);

            $this->extendTrust($trustedDevice, $reactivatedAt, $this->randomDateBetween($reactivatedAt, $now));
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Revoked, $revokedAt);
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Reactivated, $reactivatedAt);
        }
    }

    /**
     * Trust the device for a new 30 days starting at $startedAt.
     */
    private function extendTrust(TrustedDevice $trustedDevice, CarbonImmutable $startedAt, CarbonInterface $lastUsedAt): void
    {
        $trustedDevice->forceFill([
            'expires_at' => $startedAt->addDays(TrustedDeviceFactory::TRUST_LIFETIME_DAYS),
            'last_used_at' => $lastUsedAt,
            'updated_at' => $lastUsedAt,
        ])->save();
    }

    private function deviceFactoryFor(string $state): TrustedDeviceFactory
    {
        return match ($state) {
            'active' => TrustedDeviceFactory::new()->active(),
            'expiringSoon' => TrustedDeviceFactory::new()->expiringSoon(),
            'expired' => TrustedDeviceFactory::new()->expired(),
            'revoked' => TrustedDeviceFactory::new()->revoked(),
            default => throw new LogicException("Unknown device state [{$state}]."),
        };
    }

    private function randomDateBetween(CarbonInterface $from, CarbonInterface $to): CarbonImmutable
    {
        return CarbonImmutable::instance(fake()->dateTimeBetween($from, $to->lessThan($from) ? $from : $to));
    }

    private function recordEvent(User $user, TrustedDevice $trustedDevice, TrustedDeviceAction $trustedDeviceAction, CarbonInterface $happenedAt): void
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
            'created_at' => $happenedAt,
            'updated_at' => $happenedAt,
        ]);
    }
}
