import type { JSX } from "react";

import { ImWindows } from "react-icons/im";
import { MdOutlineLaptopMac, MdPhoneAndroid } from "react-icons/md";

import { Smartphone } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

/**
 * Resolve the human-friendly label for a trusted device. Falls back to the
 * raw user agent and finally to the given placeholder when both are absent.
 *
 * @param device The trusted device to name.
 * @param unknownDeviceLabel The translated text for a device without name or user agent.
 * @returns The device name, its user agent or the placeholder.
 */
export function deviceLabel(device: TrustedDevice, unknownDeviceLabel: string): string {
  return device.name ?? device.userAgent ?? unknownDeviceLabel;
}

/**
 * Build the "Browser / OS" secondary line for a device row, skipping any
 * missing piece so the result never shows a dangling separator.
 *
 * @example
 * deviceBrowserAndOs(device)                  // "Chrome 152 / macOS"
 * deviceBrowserAndOs({ browser: null, ... })  // "macOS"
 * deviceBrowserAndOs({ browser: "", ... })    // "macOS"
 * deviceBrowserAndOs({ ... no browser/os }, t("…values.unknown"))  // the translated "Unknown"
 *
 * @param device The trusted device to describe.
 * @param unknownLabel The translated text for a device without browser and system.
 * @returns The browser and system, or the placeholder.
 */
export function deviceBrowserAndOs(device: TrustedDevice, unknownLabel: string): string {
  const browserLabel = [device.browser, device.browserVersion]
    .filter((value) => value !== null && value !== "")
    .join(" ");

  const parts = [browserLabel, device.osName]
    .filter((value) => value !== null && value !== "")
    .join(" / ");

  return parts === "" ? unknownLabel : parts;
}

interface DeviceIconSource {
  osName: string | null;
  isMobile: boolean;
}

/**
 * Pick an icon component for a trusted device based on its OS name and
 * mobile flag. The caller supplies the className so the consumer controls
 * sizing and color tokens.
 */
export function getDeviceIcon(source: DeviceIconSource, className: string): JSX.Element {
  const os = source.osName?.toLowerCase() ?? "";
  const isMobile = source.isMobile;

  if (isMobile) {
    if (os.includes("apple") || os.includes("mac")) {
      return <Smartphone className={className} />;
    }

    return <MdPhoneAndroid className={className} />;
  }

  if (os.includes("mac")) {
    return <MdOutlineLaptopMac className={className} />;
  }

  return <ImWindows className={className} />;
}
