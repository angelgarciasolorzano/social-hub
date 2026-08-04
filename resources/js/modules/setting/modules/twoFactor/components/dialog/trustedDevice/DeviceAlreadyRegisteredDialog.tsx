import type { JSX } from "react";
import { Fragment } from "react";

import dayjs from "dayjs";
import type { LucideIcon } from "lucide-react";
import { ArrowRight, CircleAlert, Clock4, Globe, MapPin, Monitor, ShieldCheck } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import type { TrustedDevice } from "../../../types/trustedDevice";

interface DeviceAlreadyRegisteredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }
  return dayjs(iso).format("D [de] MMMM [del] YYYY, h:mm A");
}

function formatTimeUntil(iso: string | null): string {
  if (iso === null || dayjs(iso).isBefore(dayjs())) {
    return "vencido";
  }

  return dayjs(iso).fromNow();
}

interface DeviceInfoItem {
  icon: LucideIcon;
  iconBgClass: string;
  iconFgClass: string;
  title: string;
  primary: string;
  badge?: string;
  badgePosition?: "before" | "after";
}

function DeviceAlreadyRegisteredDialog({
  existingDevice,
  open,
  onClose,
}: DeviceAlreadyRegisteredDialogProps): JSX.Element {
  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          onClose();
        }
      }}
    >
      <DialogContent className="max-w-4xl min-w-3xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5 text-muted-foreground" />
              Dispositivo ya registrado
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo ya esta registrado como de confianza en tu cuenta. No es necesario
            agregarlo nuevamente.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <DeviceDetailsHeader />

        <DeviceInfoCard existingDevice={existingDevice} />

        <AlreadyRegisteredActionsAlert />

        <DialogFooter>
          <Button type="button" onClick={onClose}>
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function DeviceDetailsHeader(): JSX.Element {
  return (
    <div className="flex flex-col gap-1">
      <span className="font-semibold">Detalles del dispositivo registrado</span>

      <p className="text-sm text-muted-foreground">
        Asi es como identificamos este dispositivo actualmente.
      </p>
    </div>
  );
}

type DeviceInfoCardProps = Pick<DeviceAlreadyRegisteredDialogProps, "existingDevice">;

function DeviceInfoCard({ existingDevice }: DeviceInfoCardProps): JSX.Element {
  const browser =
    existingDevice.browser !== null && existingDevice.browser !== ""
      ? existingDevice.browser
      : "Desconocido";

  const osName =
    existingDevice.osName !== null && existingDevice.osName !== ""
      ? existingDevice.osName
      : "Desconocido";

  const ip = existingDevice.ip ?? "No disponible";

  const primaryRow: DeviceInfoItem[] = [
    {
      icon: Globe,
      iconBgClass: "bg-blue-100/50 dark:bg-blue-900/20",
      iconFgClass: "text-blue-700 dark:text-blue-500",
      title: "Navegador",
      primary: browser,
    },
    {
      icon: Monitor,
      iconBgClass: "bg-violet-100/50 dark:bg-violet-900/20",
      iconFgClass: "text-violet-700 dark:text-violet-500",
      title: "Sistema operativo",
      primary: osName,
    },
    {
      icon: MapPin,
      iconBgClass: "bg-orange-100/50 dark:bg-orange-900/20",
      iconFgClass: "text-orange-700 dark:text-orange-500",
      title: "Direccion IP",
      primary: ip,
    },
  ];

  const secondaryRow: DeviceInfoItem[] = [
    {
      icon: Clock4,
      iconBgClass: "bg-cyan-100/50 dark:bg-cyan-900/20",
      iconFgClass: "text-cyan-700 dark:text-cyan-500",
      title: "Ultimo acceso",
      primary: formatLongDate(existingDevice.lastUsedAt),
      badge:
        existingDevice.lastUsedAt !== null ? dayjs(existingDevice.lastUsedAt).fromNow() : undefined,
    },
    {
      icon: MapPin,
      iconBgClass: "bg-green-100/50 dark:bg-green-900/20",
      iconFgClass: "text-green-700 dark:text-green-500",
      title: "Expira el",
      primary: formatLongDate(existingDevice.expiresAt),
      badge: formatTimeUntil(existingDevice.expiresAt),
      badgePosition: "after",
    },
  ];

  return (
    <div className="flex flex-col gap-8 rounded-xl border bg-card p-6 shadow-sm dark:bg-input/10">
      <DeviceInfoRow items={primaryRow} />

      <Separator />

      <DeviceInfoRow items={secondaryRow} columns={2} />
    </div>
  );
}

interface DeviceInfoRowProps {
  items: DeviceInfoItem[];
  columns?: 2 | 3;
}

function DeviceInfoRow({ items, columns = 3 }: DeviceInfoRowProps): JSX.Element {
  const gridCols = columns === 2 ? "grid-cols-[2fr_auto_2fr]" : "grid-cols-[2fr_auto_2fr_auto_2fr]";

  return (
    <div className={`grid items-stretch gap-8 ${gridCols}`}>
      {items.map((item, index) => (
        <Fragment key={item.title}>
          <DeviceInfoItem item={item} />

          {index < items.length - 1 && <Separator orientation="vertical" />}
        </Fragment>
      ))}
    </div>
  );
}

interface DeviceInfoItemProps {
  item: DeviceInfoItem;
}

function DeviceInfoItem({ item }: DeviceInfoItemProps): JSX.Element {
  const Icon = item.icon;

  const badgePosition = item.badgePosition ?? "before";
  const showBadge = item.badge !== undefined && item.badge !== "";

  const badge = showBadge ? (
    <Badge className="mt-1.5 block bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300">
      {item.badge}
    </Badge>
  ) : null;

  return (
    <div className="flex gap-4">
      <div className={`flex h-10 w-10 rounded-md p-2 ${item.iconBgClass}`}>
        <Icon className={`h-6 w-6 ${item.iconFgClass}`} />
      </div>

      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium">{item.title}</span>

        {badgePosition === "before" && badge}

        <span className="text-sm text-muted-foreground">{item.primary}</span>

        {badgePosition === "after" && badge}
      </div>
    </div>
  );
}

function AlreadyRegisteredActionsAlert(): JSX.Element {
  return (
    <Alert className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
      <CircleAlert />
      <AlertTitle>¿Necesitar hacer cambios?</AlertTitle>
      <AlertDescription>
        Puedes administrar este dispositivo desde la lista de dispositivos de confianza.
        <Button size="xs" variant="outline">
          Ir a dispositivos de confianza
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default DeviceAlreadyRegisteredDialog;
