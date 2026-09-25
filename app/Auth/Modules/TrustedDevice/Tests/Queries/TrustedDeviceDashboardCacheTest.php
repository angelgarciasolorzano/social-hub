<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('uses warm per-user cache entries for stats and recent activity', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    createTrustedDeviceEvent($user);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $coldStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user));
    $warmStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user));

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

it('expires recent activity after 30 seconds and stats after 60 seconds', function (): void {
    $user = createUser();
    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $request = request();

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, $request);

    createTrustedDevice($user);
    $trustedDeviceEvent = createTrustedDeviceEvent($user);

    $this->travel(31)->seconds();

    $warmStatsBeforeExpiry = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user));

    $expiredRecentActivity = measureTrustedDeviceTableQueries(
        fn (): array => $trustedDeviceDashboardCache->recentActivity($user, $request),
        'trusted_device_events',
    );

    $this->travel(30)->seconds();

    $expiredStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($user));

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

    $firstStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($firstUser));
    $secondStats = measureTrustedDeviceTableQueries(fn (): array => $trustedDeviceDashboardCache->stats($secondUser));

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
