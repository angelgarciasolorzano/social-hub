import type { JSX } from "react";

import { ImWindows } from "react-icons/im";
import { MdOutlineLaptopMac, MdPhoneAndroid } from "react-icons/md";

import { Smartphone } from "lucide-react";

import type { TrustedDevice } from "../types/trustedDevice";

/**
 * Resolve the human-friendly label for a trusted device. Falls back to the
 * raw user agent and finally to a generic placeholder when both are absent.
 */
export function deviceLabel(device: TrustedDevice): string {
  return device.name ?? device.userAgent ?? "Dispositivo desconocido";
}

/**
 * Pick an icon component for a trusted device based on its OS name and
 * mobile flag. The caller supplies the className so the consumer controls
 * sizing and color tokens.
 */
export function getDeviceIcon(device: TrustedDevice, className: string): JSX.Element {
  const os = device.osName?.toLowerCase() ?? "";
  const isMobile = device.isMobile;

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
