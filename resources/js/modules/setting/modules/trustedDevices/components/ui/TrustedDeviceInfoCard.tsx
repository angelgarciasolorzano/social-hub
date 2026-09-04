import type { JSX } from "react";
import { Fragment } from "react";

import { CalendarRange, Clock4, Globe, MapPin } from "lucide-react";

import DeviceMetadataItem, {
  type DeviceMetadataItemProps,
} from "@/modules/setting/modules/trustedDevices/components/ui/DeviceMetadataItem";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevices/utils/valueOrFallback";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { Separator } from "@/shared/components/shadcn/ui/separator";

import { iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface TrustedDeviceDetailsHeaderProps {
  description: string;
  title: string;
}

export function TrustedDeviceDetailsHeader({
  title,
  description,
}: TrustedDeviceDetailsHeaderProps): JSX.Element {
  return (
    <div className="flex flex-col gap-1">
      <span className="font-semibold">{title}</span>

      <p className="text-sm text-muted-foreground">{description}</p>
    </div>
  );
}

interface TrustedDeviceInfoCardProps {
  device: TrustedDevice;
  expirationLabel?: string;
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

  const primaryRow: Omit<DeviceMetadataItemProps, "badge" | "badgePosition">[] = [
    {
      icon: <Globe className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />,
      iconColor: "blue",
      title: "Navegador",
      description: browser,
    },
    {
      icon: getDeviceIcon(device, cn("h-6 w-6", iconColorVariants.violet.iconFgClass)),
      iconColor: "violet",
      title: "Sistema operativo",
      description: osName,
    },
    {
      icon: <MapPin className={cn("h-6 w-6", iconColorVariants.orange.iconFgClass)} />,
      iconColor: "orange",
      title: "Direccion IP",
      description: ip,
    },
  ];

  const secondaryRow: DeviceMetadataItemProps[] = [
    {
      icon: <Clock4 className={cn("h-6 w-6", iconColorVariants.cyan.iconFgClass)} />,
      iconColor: "cyan",
      title: "Ultimo acceso",
      description: formatLongDate(device.lastUsedAt),
      badge: device.lastUsedAt !== null ? fromNow(device.lastUsedAt) : undefined,
    },
    {
      icon: <CalendarRange className={cn("h-6 w-6", iconColorVariants.green.iconFgClass)} />,
      iconColor: "green",
      title: expirationLabel,
      description: formatLongDate(device.expiresAt),
      badge: formatTimeUntil(device.expiresAt),
      badgePosition: "after",
    },
  ];

  return (
    <div className="flex flex-col gap-8 rounded-xl border bg-card p-6 shadow-sm dark:bg-input/10">
      <DeviceInfoRow items={primaryRow} />

      <Separator />

      <DeviceInfoRow columns={2} items={secondaryRow} />
    </div>
  );
}

interface DeviceInfoRowProps {
  items: DeviceMetadataItemProps[];
  columns?: 2 | 3;
}

function DeviceInfoRow({ items, columns = 3 }: DeviceInfoRowProps): JSX.Element {
  const gridCols = columns === 2 ? "grid-cols-[2fr_auto_2fr]" : "grid-cols-[2fr_auto_2fr_auto_2fr]";

  return (
    <div className={`grid items-stretch gap-8 ${gridCols}`}>
      {items.map((item, index) => (
        <Fragment key={item.title}>
          <DeviceMetadataItem {...item} />

          {index < items.length - 1 && <Separator orientation="vertical" />}
        </Fragment>
      ))}
    </div>
  );
}
