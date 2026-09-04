<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

use DeviceDetector\DeviceDetector;

trait InfersDeviceMetadata
{
    /**
     * Resolve a human-friendly device name from the parsed DeviceDetector.
     */
    private function inferDeviceName(DeviceDetector $deviceDetector): string
    {
        $model = $deviceDetector->getModel();

        if ($model !== '') {
            return $model;
        }

        $os = $deviceDetector->getOs();

        if (\is_array($os)) {
            $osName = $os['name'] ?? null;

            if (\is_string($osName) && $osName !== '') {
                return match (\strtolower($osName)) {
                    'mac', 'macos', 'mac os x' => 'Mac OS',
                    'windows' => 'Windows PC',
                    'linux' => 'Linux',
                    default => $osName,
                };
            }
        }

        return $deviceDetector->getBrandName();
    }

    /**
     * Resolve the browser name (e.g. "Chrome") from the parsed DeviceDetector.
     */
    private function inferBrowserName(DeviceDetector $deviceDetector): string
    {
        $client = $deviceDetector->getClient();

        if (! \is_array($client)) {
            return '';
        }

        $name = $client['name'] ?? null;

        return \is_string($name) ? $name : '';
    }

    /**
     * Resolve the major browser version (e.g. "152") from the parsed DeviceDetector.
     */
    private function inferBrowserVersion(DeviceDetector $deviceDetector): string
    {
        $client = $deviceDetector->getClient();

        if (! \is_array($client)) {
            return '';
        }

        $version = $client['version'] ?? null;

        if (! \is_string($version) || $version === '') {
            return '';
        }

        $major = explode('.', $version, 2)[0];

        return $major;
    }

    /**
     * Resolve OS name and version from the parsed DeviceDetector.
     *
     * @return array{name: string, version: string}
     */
    private function inferOsInfo(DeviceDetector $deviceDetector): array
    {
        $os = $deviceDetector->getOs();

        if (! \is_array($os)) {
            return ['name' => '', 'version' => ''];
        }

        $name = $os['name'] ?? null;
        $version = $os['version'] ?? null;

        return [
            'name' => \is_string($name) ? $name : '',
            'version' => \is_string($version) ? $version : '',
        ];
    }

    /**
     * Resolve whether the device is a mobile or tablet (true) vs desktop/bot (false).
     */
    private function inferIsMobile(DeviceDetector $deviceDetector): bool
    {
        if ($deviceDetector->isMobile()) {
            return true;
        }

        return $deviceDetector->isTablet();
    }
}
