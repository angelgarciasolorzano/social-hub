import type { JSX } from "react";
import { Fragment } from "react";

import { CalendarRange, Clock4, Globe, MapPin } from "lucide-react";

import TrustedDeviceMetadataItem, {
  type TrustedDeviceMetadataItemProps,
} from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceMetadataItem";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
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
      {title.trim() !== "" && <h3 className="text-lg font-semibold">{title}</h3>}

      <p className="text-sm font-normal text-muted-foreground">{description}</p>
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

  const primaryMetadata: Omit<TrustedDeviceMetadataItemProps, "badge" | "badgePosition">[] = [
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

  const secondaryMetadata: TrustedDeviceMetadataItemProps[] = [
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
    <div className="flex flex-col gap-8 rounded-xl border bg-card p-6 shadow-sm dark:bg-input/20">
      <DeviceMetadataRow metadataItems={primaryMetadata} />

      <Separator />

      <DeviceMetadataRow columns={2} metadataItems={secondaryMetadata} />
    </div>
  );
}

interface DeviceMetadataRowProps {
  metadataItems: TrustedDeviceMetadataItemProps[];
  columns?: 2 | 3;
}

function DeviceMetadataRow({ metadataItems, columns = 3 }: DeviceMetadataRowProps): JSX.Element {
  const gridCols = columns === 2 ? "grid-cols-[2fr_auto_2fr]" : "grid-cols-[2fr_auto_2fr_auto_2fr]";
  const visibleMetadataItems = metadataItems.filter((metadataItem) => {
    const hasTitle = metadataItem.title.trim() !== "";
    const hasDescription =
      metadataItem.description !== undefined &&
      metadataItem.description !== null &&
      metadataItem.description.trim() !== "";
    const hasBadge = metadataItem.badge !== undefined && metadataItem.badge.trim() !== "";

    return hasTitle && (hasDescription || hasBadge);
  });

  return (
    <div className={`grid items-stretch gap-8 ${gridCols}`}>
      {visibleMetadataItems.map((metadataItem, index) => (
        <Fragment key={metadataItem.title}>
          <TrustedDeviceMetadataItem {...metadataItem} />

          {index < visibleMetadataItems.length - 1 && (
            <Separator aria-hidden="true" orientation="vertical" />
          )}
        </Fragment>
      ))}
    </div>
  );
}
