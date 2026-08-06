import type { JSX } from "react";
import { Fragment } from "react";

import { ArrowRight, CircleAlert, Clock4, Globe, MapPin, Monitor, ShieldCheck } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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

import { alertVariants } from "@/shared/lib/styling";

import type { TrustedDevice } from "../../../types/trustedDevice";
import { formatLongDate, formatTimeUntil, fromNow } from "../../../utils/dateTime";
import { valueOrFallback } from "../../../utils/valueOrFallback";
import DeviceMetadataItem, { type DeviceMetadataItemProps } from "../../ui/DeviceMetadataItem";

interface DeviceAlreadyRegisteredDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
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
  const browser = valueOrFallback(existingDevice.browser, "Desconocido");
  const osName = valueOrFallback(existingDevice.osName, "Desconocido");
  const ip = valueOrFallback(existingDevice.ip, "No disponible");

  const primaryRow: Omit<DeviceMetadataItemProps, "badge" | "badgePosition">[] = [
    {
      icon: Globe,
      iconColor: "blue",
      title: "Navegador",
      description: browser,
    },
    {
      icon: Monitor,
      iconColor: "violet",
      title: "Sistema operativo",
      description: osName,
    },
    {
      icon: MapPin,
      iconColor: "orange",
      title: "Direccion IP",
      description: ip,
    },
  ];

  const secondaryRow: DeviceMetadataItemProps[] = [
    {
      icon: Clock4,
      iconColor: "cyan",
      title: "Ultimo acceso",
      description: formatLongDate(existingDevice.lastUsedAt),
      badge: existingDevice.lastUsedAt !== null ? fromNow(existingDevice.lastUsedAt) : undefined,
    },
    {
      icon: MapPin,
      iconColor: "green",
      title: "Expira el",
      description: formatLongDate(existingDevice.expiresAt),
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

function AlreadyRegisteredActionsAlert(): JSX.Element {
  return (
    <Alert className={alertVariants.info}>
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
