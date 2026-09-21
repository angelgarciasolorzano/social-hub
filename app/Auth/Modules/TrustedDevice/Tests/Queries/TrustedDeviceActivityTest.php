<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

it('returns the default filters and a paginated, empty activity log', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/trustedDevice/TrustedDevice')
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityFilters.action', null)
                ->where('activityDialog.activityFilters.sinceDays', null)
                ->where('activityDialog.activityFilters.search', '')
                ->has('activityDialog.activityLog.data', 0)
                ->where('activityDialog.activityLog.total', 0)
            )
        );
});

it("only includes the authenticated user's events", function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created]);

    $otherUser = createUser();
    createTrustedDeviceEvent($otherUser, ['action' => TrustedDeviceAction::Created]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityLog.total', 1)
            )
        );
});

it('filters by action', function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created]);
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Renamed]);
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Revoked]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['action' => 'created,renamed']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityFilters.action', ['created', 'renamed'])
                ->where('activityDialog.activityLog.total', 2)
                ->has('activityDialog.activityLog.data', 2)
            )
        );
});

it('ignores an action value outside the whitelist', function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['action' => 'not-a-real-action']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityFilters.action', null)
                ->where('activityDialog.activityLog.total', 1)
            )
        );
});

it('filters by since_days', function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created, 'created_at' => Date::now()->subDays(3)]);
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Created, 'created_at' => Date::now()->subDays(40)]);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['since_days' => '7']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityFilters.sinceDays', ['7'])
                ->where('activityDialog.activityLog.total', 1)
            )
        );
});

it('filters by search across device label, ip and os name', function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['device_label' => 'Work MacBook', 'ip' => '203.0.113.5']);
    createTrustedDeviceEvent($user, ['device_label' => 'Home PC', 'ip' => '198.51.100.9']);

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index', ['search' => 'macbook']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->where('activityDialog.activityFilters.search', 'macbook')
                ->where('activityDialog.activityLog.total', 1)
                ->where('activityDialog.activityLog.data.0.deviceLabel', 'Work MacBook')
            )
        );
});

it('paginates the activity log at 5 per page', function (): void {
    $user = createUser();

    for ($index = 0; $index < 7; $index++) {
        createTrustedDeviceEvent($user);
    }

    $this->actingAs($user)
        ->get(route('setting.security.trusted-devices.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->reloadOnly('activityDialog', fn (Assert $assert): Assert => $assert
                ->has('activityDialog.activityLog.data', 5)
                ->where('activityDialog.activityLog.total', 7)
            )
        );
});
