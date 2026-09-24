<?php

declare(strict_types=1);

it('populates device platform details for factory-created events', function (): void {
    $trustedDeviceEvent = createTrustedDeviceEvent();

    expect($trustedDeviceEvent->device_is_mobile)
        ->toBeBool()
        ->and($trustedDeviceEvent->device_os_name)
        ->toBeIn(['Windows', 'Mac', 'Linux']);
});
