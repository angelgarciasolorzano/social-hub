import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { deviceLabel, getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface TrustedDeviceSummaryCardProps {
  device: TrustedDevice;
  lastUsedAt: string;
  expiration?: string;
}

function TrustedDeviceSummaryCard({
  device,
  lastUsedAt,
  expiration,
}: TrustedDeviceSummaryCardProps): JSX.Element {
  const deviceName = deviceLabel(device).trim() || "Dispositivo desconocido";
  const hasLastUsedAt = lastUsedAt.trim() !== "";
  const hasBrowser = device.browser !== null && device.browser.trim() !== "";
  const hasExpiration = expiration !== undefined && expiration.trim() !== "";
  const browserDetails = [
    device.osName,
    [device.browser, device.browserVersion]
      .filter((value) => value !== null && value.trim() !== "")
      .join(" "),
  ]
    .filter((value) => value !== null && value.trim() !== "")
    .join(" - ");

  return (
    <div className="flex min-w-0 items-start gap-2.5 rounded-xl border p-4 shadow-xs dark:bg-input/20">
      <div
        className={cn(
          iconColorVariants.violet.iconBgClass,
          "flex h-14 w-14 shrink-0 rounded-md border border-violet-100 p-2 dark:border-violet-200/10",
        )}
      >
        {getDeviceIcon(device, cn("h-10 w-10", iconColorVariants.violet.iconFgClass))}
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
        <h3 className="block truncate text-sm font-medium" title={deviceName}>
          {deviceName}
        </h3>

        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          {hasBrowser && (
            <dl className="flex min-w-0 items-center truncate text-xs text-muted-foreground">
              <dt className="sr-only">Sistema operativo y navegador</dt>
              <dd className="m-0 truncate">{browserDetails}</dd>
            </dl>
          )}

          {hasBrowser && hasLastUsedAt && (
            <FaCircle aria-hidden="true" className="h-1 w-1 shrink-0" />
          )}

          {hasLastUsedAt && (
            <dl className="flex min-w-0 items-center gap-1 truncate text-xs text-muted-foreground">
              <dt className="shrink-0 font-medium">Último uso:</dt>
              <dd className="m-0 truncate">{lastUsedAt}</dd>
            </dl>
          )}
        </div>

        {hasExpiration && (
          <dl className="flex items-center gap-1 text-xs text-muted-foreground">
            <dt className="font-medium">Fecha de expiración:</dt>
            <dd className="m-0">{expiration}</dd>
          </dl>
        )}
      </div>
    </div>
  );
}

export default TrustedDeviceSummaryCard;
