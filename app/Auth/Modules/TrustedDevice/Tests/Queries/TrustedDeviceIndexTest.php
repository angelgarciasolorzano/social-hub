<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use UnexpectedValueException;

it('redirects guests to the login page', function (): void {
    $this->get(route('setting.security.trusted-devices.index'))
        ->assertRedirect(route('login'));
});

it('renders the page with default filters', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/trustedDevice/TrustedDevice')
            ->where('filters.search', '')
            ->where('filters.status', null)
            ->where('filters.browser', null)
            ->where('filters.deviceType', null)
            ->where('filters.lastAccess', null)
            ->where('filters.sort', 'most-recent')
            ->where('filters.perPage', 15)
        );
});

it('defers the table, stats and recent activity until their deferred request', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Work laptop']);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->missing('trustedDevices')
            ->missing('stats')
            ->missing('recentActivity')
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('trustedDevices.data.0.name', 'Work laptop')
                ->has('stats')
                ->has('recentActivity')
            )
        );
});

it("only lists the authenticated user's devices", function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'My laptop']);
    createTrustedDevice($user, ['name' => 'My phone']);

    $otherUser = createUser();
    createTrustedDevice($otherUser, ['name' => "Someone else's laptop"]);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'));

    $trustedDevicePagination = trustedDevicePaginationData($testResponse);
    $names = collect($trustedDevicePagination['data'])->pluck('name');

    expect($trustedDevicePagination['total'])->toBe(2)
        ->and($names)->toContain('My laptop')
        ->and($names)->toContain('My phone')
        ->and($names)->not->toContain("Someone else's laptop");
});

it('filters by search across name, browser and os', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Work MacBook']);
    createTrustedDevice($user, ['name' => 'Home PC']);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['search' => 'macbook']));

    $names = collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names)->toContain('Work MacBook');
});

it('filters by revoked status', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Active device']);
    $trustedDevice = createTrustedDevice($user, ['name' => 'Revoked device']);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['status' => 'revoked']));

    $names = collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names)->toContain('Revoked device');
});

it('filters by active and inactive status', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Active device', 'expires_at' => Date::now()->addDay()]);
    createTrustedDevice($user, ['name' => 'Expired device', 'expires_at' => Date::now()->subDay()]);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['status' => 'active']));

    $inactiveResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['status' => 'inactive']));

    expect(collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name')->all())
        ->toBe(['Active device'])
        ->and(collect(trustedDevicePaginationData($inactiveResponse)['data'])->pluck('name')->all())
        ->toBe(['Expired device']);
});

it('filters by browser', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Chrome device', 'browser' => 'Chrome 120']);
    createTrustedDevice($user, ['name' => 'Firefox device', 'browser' => 'Firefox 118']);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['browser' => 'chrome']));

    $names = collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names)->toContain('Chrome device');
});

it('filters by device type', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Desktop device', 'is_mobile' => false]);
    createTrustedDevice($user, ['name' => 'Mobile device', 'is_mobile' => true]);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['device_type' => 'mobile / tablet']));

    $names = collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names)->toContain('Mobile device');
});

it('filters by last access window', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Recently used', 'last_used_at' => Date::now()->subHours(2)]);
    createTrustedDevice($user, ['name' => 'Used a month ago', 'last_used_at' => Date::now()->subDays(40)]);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['last_access' => '24h']));

    $names = collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name');

    expect($names)->toHaveCount(1)
        ->and($names)->toContain('Recently used');
});

it('sorts by name ascending and descending', function (): void {
    $user = createUser();
    createTrustedDevice($user, ['name' => 'Zeta']);
    createTrustedDevice($user, ['name' => 'Alpha']);

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['sort' => 'name-asc']));

    $descendingResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['sort' => 'name-desc']));

    expect(collect(trustedDevicePaginationData($testResponse)['data'])->pluck('name')->all())
        ->toBe(['Alpha', 'Zeta'])
        ->and(collect(trustedDevicePaginationData($descendingResponse)['data'])->pluck('name')->all())
        ->toBe(['Zeta', 'Alpha']);
});

it('falls back to the default sort for an invalid value', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['sort' => 'not-a-real-sort']))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('filters.sort', 'most-recent'));
});

it('falls back to the default page size for an invalid per_page value', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['per_page' => 999]))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('filters.perPage', 15));
});

it('honors a valid per_page value', function (): void {
    $user = createUser();

    for ($index = 0; $index < 6; $index++) {
        createTrustedDevice($user);
    }

    $testResponse = $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['per_page' => 5]));

    $trustedDevicePagination = trustedDevicePaginationData($testResponse);

    expect($trustedDevicePagination['data'])->toHaveCount(5)
        ->and($trustedDevicePagination['total'])->toBe(6);
});

/**
 * @template TResponse of \Symfony\Component\HttpFoundation\Response
 *
 * @param  TestResponse<TResponse>  $testResponse
 * @return array{data: array<int, array<string, mixed>>, total: int}
 */
function trustedDevicePaginationData(TestResponse $testResponse): array
{
    $trustedDevicePagination = null;

    $testResponse->assertInertia(function (Assert $assert) use (&$trustedDevicePagination): void {
        $assert->loadDeferredProps(function (Assert $assert) use (&$trustedDevicePagination): void {
            $pageProps = $assert->toArray()['props'] ?? null;

            throw_if(! is_array($pageProps) || ! array_key_exists('trustedDevices', $pageProps), UnexpectedValueException::class, 'The trusted devices prop is missing from the Inertia response.');

            $trustedDevicePagination = $pageProps['trustedDevices'];
        });
    });

    throw_unless(is_array($trustedDevicePagination), UnexpectedValueException::class, 'The trusted devices prop must be an array.');

    $paginationData = $trustedDevicePagination['data'] ?? null;
    $paginationTotal = $trustedDevicePagination['total'] ?? null;

    throw_if(! is_array($paginationData) || ! array_is_list($paginationData) || ! is_int($paginationTotal), UnexpectedValueException::class, 'The trusted devices pagination data has an invalid shape.');

    $devices = [];

    foreach ($paginationData as $deviceData) {
        throw_unless(is_array($deviceData), UnexpectedValueException::class, 'Each trusted device must be represented as an array.');

        $deviceAttributes = [];

        foreach ($deviceData as $attribute => $value) {
            throw_unless(is_string($attribute), UnexpectedValueException::class, 'Trusted device attributes must use string keys.');

            $deviceAttributes[$attribute] = $value;
        }

        $devices[] = $deviceAttributes;
    }

    return [
        'data' => $devices,
        'total' => $paginationTotal,
    ];
}
