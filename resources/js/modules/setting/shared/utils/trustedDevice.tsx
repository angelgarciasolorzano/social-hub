import type { JSX } from "react";

import { ImWindows } from "react-icons/im";
import { MdOutlineLaptopMac, MdPhoneAndroid } from "react-icons/md";

import { Smartphone } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

/**
 * Resolve the human-friendly label for a trusted device. Falls back to the
 * raw user agent and finally to a generic placeholder when both are absent.
 */
export function deviceLabel(device: TrustedDevice): string {
  return device.name ?? device.userAgent ?? "Dispositivo desconocido";
}

/**
 * Build the "Browser / OS" secondary line for a device row, skipping any
 * missing piece so the result never shows a dangling separator.
 *
 * @example
 * deviceBrowserAndOs(device)                  // "Chrome 152 / macOS"
 * deviceBrowserAndOs({ browser: null, ... })  // "macOS"
 * deviceBrowserAndOs({ browser: "", ... })    // "macOS"
 * deviceBrowserAndOs({ ... no browser/os })    // "Desconocido"
 */
export function deviceBrowserAndOs(device: TrustedDevice): string {
  const browserLabel = [device.browser, device.browserVersion]
    .filter((value) => value !== null && value !== "")
    .join(" ");

  const parts = [browserLabel, device.osName]
    .filter((value) => value !== null && value !== "")
    .join(" / ");

  return parts === "" ? "Desconocido" : parts;
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
