import type { JSX, ReactNode } from "react";

import { CalendarRange, Clock4, Globe, MapPin } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface TrustedDeviceInfoCardProps {
  device: TrustedDevice;
  expirationLabel?: string;
}

interface TrustedDeviceInfoItemProps {
  detail?: string;
  icon: ReactNode;
  iconColor: IconColorVariant;
  label: string;
  value: string;
  valueClassName?: string;
}

export function TrustedDeviceInfoCard({
  device,
  expirationLabel = "Expira el",
}: TrustedDeviceInfoCardProps): JSX.Element {
  const browser = valueOrFallback(
    [device.browser, device.browserVersion]
      .filter((value) => value !== null && value !== "")
      .join(" "),
    "Desconocido",
  );

  const osName = valueOrFallback(device.osName, "Desconocido");
  const ip = valueOrFallback(device.ip, "No disponible");

  const primaryMetadata: TrustedDeviceInfoItemProps[] = [
    {
      icon: <Globe className={cn("size-5", iconColorVariants.blue.iconFgClass)} />,
      iconColor: "blue",
      label: "Navegador",
      value: browser,
    },
    {
      icon: getDeviceIcon(device, cn("size-5", iconColorVariants.violet.iconFgClass)),
      iconColor: "violet",
      label: "Sistema",
      value: osName,
    },
    {
      icon: <MapPin className={cn("size-5", iconColorVariants.orange.iconFgClass)} />,
      iconColor: "orange",
      label: "Dirección IP",
      value: ip,
      valueClassName: "break-all font-mono text-sm",
    },
  ];

  const secondaryMetadata: TrustedDeviceInfoItemProps[] = [
    {
      detail: formatLongDate(device.lastUsedAt),
      icon: <Clock4 className={cn("size-5", iconColorVariants.cyan.iconFgClass)} />,
      iconColor: "cyan",
      label: "Último acceso",
      value: fromNow(device.lastUsedAt),
    },
    {
      detail: formatLongDate(device.expiresAt),
      icon: <CalendarRange className={cn("size-5", iconColorVariants.green.iconFgClass)} />,
      iconColor: "green",
      label: expirationLabel,
      value: formatTimeUntil(device.expiresAt),
    },
  ];

  return (
    <section className="overflow-hidden rounded-xl border bg-card shadow-xs dark:bg-input/20">
      <div className="border-b bg-muted/30 px-4 py-3 sm:px-5">
        <h3 className="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase">
          Así identificamos este dispositivo
        </h3>
      </div>

      <div className="grid grid-cols-1 divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        {primaryMetadata.map((metadataItem) => (
          <TrustedDeviceInfoItem key={metadataItem.label} {...metadataItem} />
        ))}
      </div>

      <div className="grid grid-cols-1 divide-y border-t sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        {secondaryMetadata.map((metadataItem) => (
          <TrustedDeviceInfoItem key={metadataItem.label} {...metadataItem} />
        ))}
      </div>
    </section>
  );
}

function TrustedDeviceInfoItem({
  detail,
  icon,
  iconColor,
  label,
  value,
  valueClassName,
}: TrustedDeviceInfoItemProps): JSX.Element {
  const colors = iconColorVariants[iconColor];

  return (
    <div className="flex min-w-0 items-center gap-3 px-4 py-4 sm:px-5">
      <div
        aria-hidden="true"
        className={cn(
          "flex size-10 shrink-0 items-center justify-center rounded-md",
          colors.iconBgClass,
        )}
      >
        {icon}
      </div>

      <dl className="min-w-0 space-y-1">
        <dt className="text-sm text-muted-foreground">{label}</dt>

        <dd
          className={cn(
            "m-0 text-base leading-tight font-semibold wrap-break-word",
            valueClassName,
          )}
        >
          {value}
        </dd>

        {detail !== undefined && (
          <dd className="m-0 text-sm wrap-break-word text-muted-foreground">{detail}</dd>
        )}
      </dl>
    </div>
  );
}
