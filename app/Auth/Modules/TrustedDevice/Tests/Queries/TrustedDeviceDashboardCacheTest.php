<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('uses warm per-user cache entries for stats and recent activity', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['is_mobile' => false]);
    createTrustedDeviceEvent($user);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $coldStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user)->toArray());
    $warmStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user)->toArray());

    $coldRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($user, $request),
        'trusted_device_events',
    );

    $warmRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($user, $request),
        'trusted_device_events',
    );

    expect($coldStats['queryCount'])->toBe(8)
        ->and($warmStats['queryCount'])->toBe(0)
        ->and($warmStats['result'])->toBe($coldStats['result'])
        ->and($warmStats['result']['total'])->toBe(1)
        ->and(array_keys($warmStats['result']))->toBe([
            'total',
            'active',
            'expiringSoon',
            'recentlyAdded',
            'inactive',
            'revoked',
            'byDeviceType',
        ])
        ->and($warmStats['result']['byDeviceType'])->toBe(['desktop' => 1, 'mobile' => 0])
        ->and($coldRecentActivity['queryCount'])->toBe(1)
        ->and($warmRecentActivity['queryCount'])->toBe(0)
        ->and($warmRecentActivity['result'])->toBe($coldRecentActivity['result'])
        ->and(array_keys($warmRecentActivity['result'][0]))->toBe([
            'id',
            'action',
            'actionLabel',
            'deviceLabel',
            'deviceIsMobile',
            'deviceOsName',
            'ip',
            'createdAt',
        ])
        ->and(Cache::has('trusted-device:dashboard:'.$user->id.':stats'))->toBeTrue()
        ->and(Cache::has('trusted-device:dashboard:'.$user->id.':recent-activity'))->toBeTrue();
});

it('keeps dashboard stats as the established deferred Inertia array', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['is_mobile' => false]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->has('stats', 7)
                ->where('stats.total', 1)
                ->where('stats.byDeviceType.desktop', 1)
                ->where('stats.byDeviceType.mobile', 0)));
});

it('expires recent activity after 30 seconds and stats after 60 seconds', function (): void {
    $user = createUser();
    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, $request);

    createTrustedDevice($user);
    $trustedDeviceEvent = createTrustedDeviceEvent($user);

    $this->travel(31)->seconds();

    $warmStatsBeforeExpiry = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user)->toArray());

    $expiredRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($user, $request),
        'trusted_device_events',
    );

    $this->travel(30)->seconds();

    $expiredStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user)->toArray());

    expect($warmStatsBeforeExpiry['queryCount'])->toBe(0)
        ->and($warmStatsBeforeExpiry['result']['total'])->toBe(0)
        ->and($expiredRecentActivity['queryCount'])->toBe(1)
        ->and($expiredRecentActivity['result'][0]['id'])->toBe($trustedDeviceEvent->id)
        ->and($expiredStats['queryCount'])->toBe(8)
        ->and($expiredStats['result']['total'])->toBe(1);
});

it('keeps dashboard cache entries isolated between users', function (): void {
    $firstUser = createUser();
    createTrustedDevice($firstUser);
    $trustedDeviceEvent = createTrustedDeviceEvent($firstUser);

    $secondUser = createUser();
    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $firstStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($firstUser)->toArray());
    $secondStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($secondUser)->toArray());

    $firstRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($firstUser, $request),
        'trusted_device_events',
    );

    $secondRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($secondUser, $request),
        'trusted_device_events',
    );

    expect($firstStats['result']['total'])->toBe(1)
        ->and($secondStats['queryCount'])->toBe(8)
        ->and($secondStats['result']['total'])->toBe(0)
        ->and($firstRecentActivity['result'])->toHaveCount(1)
        ->and($firstRecentActivity['result'][0]['id'])->toBe($trustedDeviceEvent->id)
        ->and($secondRecentActivity['queryCount'])->toBe(1)
        ->and($secondRecentActivity['result'])
        ->toBeEmpty();
});

it('continues querying filtered device lists and activity dialogs on every request', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Work laptop']);
    createTrustedDeviceEvent($user);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, request());

    $filteredDeviceRequest = fn (): mixed => $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['search' => 'Work']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert->has('trustedDevices.data', 1)));

    $firstFilteredDeviceRequest = measureTrustedDeviceTableQueries($filteredDeviceRequest, 'trusted_devices', 'like');
    $secondFilteredDeviceRequest = measureTrustedDeviceTableQueries($filteredDeviceRequest, 'trusted_devices', 'like');

    $activityDialogRequest = fn (): mixed => $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert->has('activityDialog.activityLog.data', 1)));

    $firstActivityDialogRequest = measureTrustedDeviceTableQueries($activityDialogRequest, 'trusted_device_events');
    $secondActivityDialogRequest = measureTrustedDeviceTableQueries($activityDialogRequest, 'trusted_device_events');

    expect($firstFilteredDeviceRequest['queryCount'])->toBe(2)
        ->and($secondFilteredDeviceRequest['queryCount'])->toBe(2)
        ->and($firstActivityDialogRequest['queryCount'])->toBe(2)
        ->and($secondActivityDialogRequest['queryCount'])->toBe(2);
});

it('invalidates dashboard cache after every successful trusted device mutation', function (): void {
    $user = createUserWithTwoFactor();

    $matchingFingerprint = [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '203.0.113.5',
    ];

    $trustedDevice = createTrustedDevice($user, [...$matchingFingerprint, 'name' => 'Revoked device']);
    $trustedDevice->delete();

    $duplicateDevice = createTrustedDevice($user, [...$matchingFingerprint, 'name' => 'Duplicate device']);

    $mutableDevice = createTrustedDevice($user, [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '203.0.113.7',
        'name' => 'Mutable device',
    ]);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $statsCacheKey = 'trusted-device:dashboard:'.$user->id.':stats';
    $recentActivityCacheKey = 'trusted-device:dashboard:'.$user->id.':recent-activity';

    $assertInvalidatedAndRefreshed = function (
        Closure $mutation,
        int $expectedTotal,
        int $expectedRevoked,
        TrustedDeviceAction $trustedDeviceAction,
    ) use ($trustedDeviceDashboardCache, $user, $request, $statsCacheKey, $recentActivityCacheKey): void {
        $trustedDeviceDashboardCache->stats($user);
        $trustedDeviceDashboardCache->recentActivity($user, $request);

        expect(Cache::has($statsCacheKey))->toBeTrue()
            ->and(Cache::has($recentActivityCacheKey))->toBeTrue();

        $this->travel(1)->seconds();
        $mutation();

        expect(Cache::has($statsCacheKey))->toBeFalse()
            ->and(Cache::has($recentActivityCacheKey))->toBeFalse();

        $stats = $trustedDeviceDashboardCache->stats($user);
        $recentActivity = $trustedDeviceDashboardCache->recentActivity($user, $request);

        expect($stats->total)->toBe($expectedTotal)
            ->and($stats->revoked)->toBe($expectedRevoked)
            ->and(array_column($recentActivity, 'action'))->toContain($trustedDeviceAction->value);
    };

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->withHeader('User-Agent', chromeWindowsUserAgent())
            ->post(route('user.trusted-devices.store'), ['name' => 'New device'])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 3,
        expectedRevoked: 0,
        trustedDeviceAction: TrustedDeviceAction::Created,
    );

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->patch(route('user.trusted-devices.update', $mutableDevice), ['name' => 'Renamed device'])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 3,
        expectedRevoked: 0,
        trustedDeviceAction: TrustedDeviceAction::Renamed,
    );

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->post(route('user.trusted-devices.renew', $mutableDevice))
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 3,
        expectedRevoked: 0,
        trustedDeviceAction: TrustedDeviceAction::Renewed,
    );

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
                'otp_code' => validOtpFor($user),
            ])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 3,
        expectedRevoked: 1,
        trustedDeviceAction: TrustedDeviceAction::Reactivated,
    );

    expect(TrustedDevice::withTrashed()->find($duplicateDevice->id))->toBeNull();

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->delete(route('user.trusted-devices.destroy', $mutableDevice), [
                'password' => 'password',
                'terms' => true,
            ])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 2,
        expectedRevoked: 2,
        trustedDeviceAction: TrustedDeviceAction::Revoked,
    );

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->delete(route('user.trusted-devices.destroy-all'), [
                'password' => 'password',
                'terms' => true,
            ])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 0,
        expectedRevoked: 4,
        trustedDeviceAction: TrustedDeviceAction::RevokedAll,
    );

    $assertInvalidatedAndRefreshed(
        fn (): mixed => $this->actingAs($user)
            ->delete(route('user.trusted-devices.force-destroy', $mutableDevice), [
                'password' => 'password',
                'terms' => true,
            ])
            ->assertInertiaFlash('type', 'success'),
        expectedTotal: 0,
        expectedRevoked: 5,
        trustedDeviceAction: TrustedDeviceAction::Revoked,
    );
});

it('keeps cache entries isolated when mutations fail or make no changes', function (): void {
    $user = createUser();

    $trustedDevice = createTrustedDevice($user, [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '127.0.0.1',
    ]);

    $otherUser = createUser();

    $otherUsersDevices = [
        createTrustedDevice($otherUser),
        createTrustedDevice($otherUser),
    ];

    $otherUsersEvents = [
        createTrustedDeviceEvent($otherUser),
        createTrustedDeviceEvent($otherUser),
    ];

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $userStatsCacheKey = 'trusted-device:dashboard:'.$user->id.':stats';
    $userActivityCacheKey = 'trusted-device:dashboard:'.$user->id.':recent-activity';
    $otherUserStatsCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':stats';
    $otherUserActivityCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':recent-activity';

    Cache::forget($userStatsCacheKey);
    Cache::forget($userActivityCacheKey);
    Cache::forget($otherUserStatsCacheKey);
    Cache::forget($otherUserActivityCacheKey);

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, $request);

    $otherUsersStats = $trustedDeviceDashboardCache->stats($otherUser)->toArray();
    $otherUsersRecentActivity = $trustedDeviceDashboardCache->recentActivity($otherUser, $request);

    $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => 'Updated name'])
        ->assertInertiaFlash('type', 'success');

    expect(Cache::has($userStatsCacheKey))->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))->toBeFalse()
        ->and(Cache::has($otherUserStatsCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($otherUser)->toArray())->toBe($otherUsersStats)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, $request))->toBe($otherUsersRecentActivity);

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, $request);

    $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $otherUsersDevices[0]), ['name' => 'Unauthorized name'])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'))
        ->assertInertiaFlash('type', 'error');

    expect(Cache::has($userStatsCacheKey))->toBeTrue()
        ->and(Cache::has($userActivityCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserStatsCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)->total)->toBe(1)
        ->and($trustedDeviceDashboardCache->stats($otherUser)->total)->toBe(2)
        ->and(array_column($trustedDeviceDashboardCache->recentActivity($user, $request), 'id'))
        ->not->toContain($otherUsersEvents[0]->id, $otherUsersEvents[1]->id);
});

/**
 * @template TResult
 *
 * @param  Closure(): TResult  $callback
 * @return array{queryCount: int, result: TResult}
 */
function measureTrustedDeviceTableQueries(Closure $callback, ?string $tableName = null, ?string $sqlFragment = null): array
{
    $connection = DB::connection();
    $wasLoggingQueries = $connection->logging();

    if (! $wasLoggingQueries) {
        $connection->enableQueryLog();
    }

    $startingQueryCount = count($connection->getQueryLog());

    try {
        $result = $callback();
    } finally {
        $queries = array_slice($connection->getQueryLog(), $startingQueryCount);

        if (! $wasLoggingQueries) {
            $connection->disableQueryLog();
        }
    }

    $queryCount = 0;

    foreach ($queries as $query) {
        $sql = strtolower($query['query']);
        $matchesTrustedDeviceTable = $tableName === null
            ? str_contains($sql, 'trusted_devices') || str_contains($sql, 'trusted_device_events')
            : str_contains($sql, $tableName);

        if (! $matchesTrustedDeviceTable) {
            continue;
        }

        if ($sqlFragment !== null && ! str_contains($sql, strtolower($sqlFragment))) {
            continue;
        }

        $queryCount++;
    }

    return [
        'queryCount' => $queryCount,
        'result' => $result,
    ];
}
