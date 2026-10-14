<?php

declare(strict_types=1);

namespace App\Auth\Database\Factories;

use App\Auth\Models\TrustedDevice;
use App\Support\Seeding\WeightedRandom;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use LogicException;
use Override;

/**
 * @extends Factory<TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TrustedDevice>
     */
    #[Override]
    protected $model = TrustedDevice::class;

    private const int MOBILE_SHARE_PERCENT = 40;

    private const int TABLET_SHARE_OF_MOBILE_PERCENT = 15;

    private const int TRUST_LIFETIME_DAYS = 30;

    /** @var array<string, int> */
    private const array DESKTOP_OS_WEIGHTS = ['Windows' => 50, 'Mac' => 30, 'Linux' => 20];

    /** @var array<string, int> */
    private const array MOBILE_OS_WEIGHTS = ['iOS' => 45, 'Android' => 55];

    /** @var array<string, array<string, int>> */
    private const array BROWSER_WEIGHTS_BY_OS = [
        'Windows' => ['Chrome' => 55, 'Edge' => 30, 'Firefox' => 15],
        'Mac' => ['Chrome' => 45, 'Safari' => 40, 'Firefox' => 15],
        'Linux' => ['Chrome' => 55, 'Firefox' => 45],
        'iOS' => ['Safari' => 70, 'Chrome' => 30],
        'Android' => ['Chrome' => 80, 'Firefox' => 20],
    ];

    /** @var array<string, list<string>> */
    private const array OS_VERSIONS = [
        'Windows' => ['10', '11'],
        'Mac' => ['14', '15'],
        'Linux' => ['22.04', '24.04'],
        'iOS' => ['17', '18'],
        'Android' => ['14', '15'],
    ];

    /** @var array<string, list<string>> */
    private const array NAMES_BY_DEVICE = [
        'Windows' => ['Computadora de trabajo', 'PC con Windows', 'Laptop Windows'],
        'Mac' => ['MacBook personal', 'MacBook de trabajo', 'iMac'],
        'Linux' => ['Laptop Linux', 'Estación de trabajo Linux'],
        'iOS' => ['iPhone personal', 'iPhone de trabajo'],
        'iPad' => ['iPad personal', 'iPad de casa'],
        'Android' => ['Teléfono Android', 'Android personal'],
        'AndroidTablet' => ['Tablet Android', 'Tablet Android de casa'],
    ];

    /**
     * Define the model's default state: a coherent device (the operating
     * system follows the device type, the browser follows the system, and the
     * user agent, version and name match them) that is trusted for 30 days.
     *
     * @return array<string, CarbonInterface|string|bool>
     *
     * @phpstan-return array<model-property<TrustedDevice>, mixed>
     */
    public function definition(): array
    {
        $isMobile = WeightedRandom::chance(self::MOBILE_SHARE_PERCENT);
        $isTablet = $isMobile && WeightedRandom::chance(self::TABLET_SHARE_OF_MOBILE_PERCENT);
        $osName = WeightedRandom::pick($isMobile ? self::MOBILE_OS_WEIGHTS : self::DESKTOP_OS_WEIGHTS);
        $browser = WeightedRandom::pick(self::BROWSER_WEIGHTS_BY_OS[$osName]);
        $osVersion = $this->osVersionFor($osName);
        $browserVersion = $browser === 'Safari' ? fake()->numberBetween(16, 18) : fake()->numberBetween(110, 140);

        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'name' => fake()->randomElement(self::NAMES_BY_DEVICE[$this->deviceKey($osName, $isTablet)]),
            'user_agent' => $this->userAgentFor($osName, $browser, (string) $browserVersion, $osVersion, $isTablet),
            'browser' => $browser,
            'browser_version' => (string) $browserVersion,
            'os_name' => $osName,
            'os_version' => $osVersion,
            'is_mobile' => $isMobile,
            'ip' => fake()->ipv4(),
            'last_used_at' => fake()->optional(0.5)->dateTimeBetween('-30 days', 'now'),
            'expires_at' => Date::now()->addDays(self::TRUST_LIFETIME_DAYS),
        ];
    }

    /**
     * A device whose 30-day trust started within the last three weeks, so it
     * is active and not close to expiring.
     */
    public function active(): static
    {
        return $this->trustStartedBetweenDaysAgo(0, 21);
    }

    /**
     * A device whose trust expires in less than a week.
     */
    public function expiringSoon(): static
    {
        return $this->trustStartedBetweenDaysAgo(24, 29);
    }

    /**
     * A device whose trust already expired; it was last used before that.
     */
    public function expired(): static
    {
        return $this->trustStartedBetweenDaysAgo(31, 120);
    }

    /**
     * A device that was revoked (soft deleted) after being used for a while.
     */
    public function revoked(): static
    {
        return $this->trustStartedBetweenDaysAgo(2, 60, isRevoked: true);
    }

    /**
     * Give the device dates that follow one another: creation, last use,
     * expiration (30 days after the trust started) and, when revoked, the
     * revocation. The device is never used after it expired or was revoked.
     */
    private function trustStartedBetweenDaysAgo(int $minDays, int $maxDays, bool $isRevoked = false): static
    {
        return $this->state(function () use ($minDays, $maxDays, $isRevoked): array {
            $now = CarbonImmutable::now();
            $startedAt = $now->subDays(fake()->numberBetween($minDays, $maxDays))->subMinutes(fake()->numberBetween(0, 1439));
            $expiresAt = $startedAt->addDays(self::TRUST_LIFETIME_DAYS);
            $revokedAt = $isRevoked ? CarbonImmutable::instance(fake()->dateTimeBetween($startedAt, $now)) : null;

            $lastUseLimit = $revokedAt ?? ($expiresAt->isFuture() ? $now : $expiresAt);
            $lastUsedAt = CarbonImmutable::instance(fake()->dateTimeBetween($startedAt, $lastUseLimit));

            return [
                'created_at' => $startedAt,
                'last_used_at' => $lastUsedAt,
                'updated_at' => $revokedAt ?? $lastUsedAt,
                'expires_at' => $expiresAt,
                'deleted_at' => $revokedAt,
            ];
        });
    }

    private function osVersionFor(string $osName): string
    {
        $versions = self::OS_VERSIONS[$osName] ?? throw new LogicException('Unsupported operating system selected by factory.');

        return $versions[array_rand($versions)];
    }

    private function deviceKey(string $osName, bool $isTablet): string
    {
        return match (true) {
            $isTablet && $osName === 'iOS' => 'iPad',
            $isTablet => 'AndroidTablet',
            default => $osName,
        };
    }

    /**
     * Build a user agent that DeviceDetector reads as this operating system,
     * browser and device type.
     */
    private function userAgentFor(string $osName, string $browser, string $browserVersion, string $osVersion, bool $isTablet): string
    {
        $chromeVersion = "{$browserVersion}.0.0.0";

        return match ($osName) {
            'Windows' => match ($browser) {
                'Firefox' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:{$browserVersion}.0) Gecko/20100101 Firefox/{$browserVersion}.0",
                'Edge' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$chromeVersion} Safari/537.36 Edg/{$chromeVersion}",
                default => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$chromeVersion} Safari/537.36",
            },
            'Mac' => match ($browser) {
                'Firefox' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:{$browserVersion}.0) Gecko/20100101 Firefox/{$browserVersion}.0",
                'Safari' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/{$browserVersion}.5 Safari/605.1.15",
                default => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$chromeVersion} Safari/537.36",
            },
            'Linux' => match ($browser) {
                'Firefox' => "Mozilla/5.0 (X11; Linux x86_64; rv:{$browserVersion}.0) Gecko/20100101 Firefox/{$browserVersion}.0",
                default => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$chromeVersion} Safari/537.36",
            },
            'iOS' => $this->iosUserAgent($browser, $browserVersion, $osVersion, $isTablet),
            'Android' => $this->androidUserAgent($browser, $browserVersion, $osVersion, $isTablet),
            default => throw new LogicException('Unsupported operating system selected by factory.'),
        };
    }

    private function iosUserAgent(string $browser, string $browserVersion, string $osVersion, bool $isTablet): string
    {
        $platform = $isTablet
            ? "iPad; CPU OS {$osVersion}_5 like Mac OS X"
            : "iPhone; CPU iPhone OS {$osVersion}_5 like Mac OS X";

        return $browser === 'Chrome'
            ? "Mozilla/5.0 ({$platform}) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/{$browserVersion}.0.0.0 Mobile/15E148 Safari/604.1"
            : "Mozilla/5.0 ({$platform}) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/{$browserVersion}.5 Mobile/15E148 Safari/604.1";
    }

    private function androidUserAgent(string $browser, string $browserVersion, string $osVersion, bool $isTablet): string
    {
        if ($browser === 'Firefox') {
            $form = $isTablet ? 'Tablet' : 'Mobile';

            return "Mozilla/5.0 (Android {$osVersion}; {$form}; rv:{$browserVersion}.0) Gecko/{$browserVersion}.0 Firefox/{$browserVersion}.0";
        }

        $model = $isTablet ? 'SM-X710' : 'Pixel 8';
        $mobileToken = $isTablet ? '' : 'Mobile ';

        return "Mozilla/5.0 (Linux; Android {$osVersion}; {$model}) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$browserVersion}.0.0.0 {$mobileToken}Safari/537.36";
    }
}
