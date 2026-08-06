import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";
import { MdOutlineLaptopMac } from "react-icons/md";

import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import type { TrustedDevice } from "../../types/trustedDevice";

interface DeviceSummaryCardProps {
  device: TrustedDevice;
  lastUsedAt: string;
  expiration?: string;
}

function DeviceSummaryCard({
  device,
  lastUsedAt,
  expiration,
}: DeviceSummaryCardProps): JSX.Element {
  const deviceLabel = (device: TrustedDevice): string => {
    return device.name ?? device.userAgent ?? "Dispositivo desconocido";
  };

  return (
    <div className="flex min-w-0 items-start gap-2.5 rounded-xl border p-4 shadow-xs dark:bg-input/10">
      <div
        className={cn(
          iconColorVariants.violet.iconBgClass,
          "flex h-14 w-14 shrink-0 rounded-md border border-violet-100 p-2 dark:border-violet-200/10",
        )}
      >
        <MdOutlineLaptopMac className={cn("h-10 w-10", iconColorVariants.violet.iconFgClass)} />
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
        <span className="block truncate font-medium" title={deviceLabel(device)}>
          {deviceLabel(device)}
        </span>

        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          {device.browser !== null && device.browser !== "" && (
            <span className="truncate text-sm text-muted-foreground">
              {device.osName} - {device.browser}
            </span>
          )}

          <FaCircle className="h-1 w-1 shrink-0" />

          <span className="truncate text-sm text-muted-foreground">Último uso: {lastUsedAt}</span>
        </div>

        {expiration !== undefined && (
          <span className="text-sm text-muted-foreground">Fecha de expiración: {expiration}</span>
        )}
      </div>
    </div>
  );
}

export default DeviceSummaryCard;
