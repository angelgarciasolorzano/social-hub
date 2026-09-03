import type { JSX } from "react";
import { Fragment } from "react";

import { router } from "@inertiajs/react";

import {
  ArrowRight,
  CalendarRange,
  CircleAlert,
  Clock4,
  Globe,
  MapPin,
  ShieldOff,
} from "lucide-react";

import DeviceMetadataItem, {
  type DeviceMetadataItemProps,
} from "@/modules/setting/modules/trustedDevices/components/ui/DeviceMetadataItem";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevices/utils/valueOrFallback";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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

import { alertVariants, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface TrustedDeviceRevokedDialogProps {
  existingDevice: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceRevokedDialog({
  existingDevice,
  open,
  onClose,
}: TrustedDeviceRevokedDialogProps): JSX.Element {
  const handleGoToRevokedList = (): void => {
    onClose();
    router.visit(index({ query: { status: "revoked" } }).url);
  };

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
              <ShieldOff className="h-5 w-5 text-muted-foreground" />
              Dispositivo revocado
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo ya fue registrado pero lo revocaste. Para volver a confiar en el,
            reactivarlo desde la lista de dispositivos revocados.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <DeviceDetailsHeader
          title="Detalles del dispositivo revocado"
          description="Asi es como identificamos este dispositivo antes de ser revocado."
        />

        <DeviceInfoCard existingDevice={existingDevice} />

        <RevokedActionsAlert onGoToRevokedList={handleGoToRevokedList} />

        <DialogFooter>
          <Button onClick={onClose} type="button" variant="outline">
            Cerrar
          </Button>
          <Button onClick={handleGoToRevokedList} type="button">
            Ir a dispositivos revocados
            <ArrowRight />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface DeviceDetailsHeaderProps {
  description: string;
  title: string;
}

function DeviceDetailsHeader({ title, description }: DeviceDetailsHeaderProps): JSX.Element {
  return (
    <div className="flex flex-col gap-1">
      <span className="font-semibold">{title}</span>

      <p className="text-sm text-muted-foreground">{description}</p>
    </div>
  );
}

type DeviceInfoCardProps = Pick<TrustedDeviceRevokedDialogProps, "existingDevice">;

function DeviceInfoCard({ existingDevice }: DeviceInfoCardProps): JSX.Element {
  const browser = valueOrFallback(existingDevice.browser, "Desconocido");
  const osName = valueOrFallback(existingDevice.osName, "Desconocido");
  const ip = valueOrFallback(existingDevice.ip, "No disponible");

  const primaryRow: Omit<DeviceMetadataItemProps, "badge" | "badgePosition">[] = [
    {
      icon: <Globe className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />,
      iconColor: "blue",
      title: "Navegador",
      description: browser,
    },
    {
      icon: getDeviceIcon(existingDevice, cn("h-6 w-6", iconColorVariants.violet.iconFgClass)),
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
      description: formatLongDate(existingDevice.lastUsedAt),
      badge: existingDevice.lastUsedAt !== null ? fromNow(existingDevice.lastUsedAt) : undefined,
    },
    {
      icon: <CalendarRange className={cn("h-6 w-6", iconColorVariants.green.iconFgClass)} />,
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

interface RevokedActionsAlertProps {
  onGoToRevokedList: () => void;
}

function RevokedActionsAlert({ onGoToRevokedList }: RevokedActionsAlertProps): JSX.Element {
  return (
    <Alert className={alertVariants.warning}>
      <CircleAlert />
      <AlertTitle>¿Quieres volver a confiar en este dispositivo?</AlertTitle>
      <AlertDescription className="flex items-center gap-4">
        Reactivarlo desde la lista de revocados restaura la fila con un token nuevo por seguridad.
        <Button onClick={onGoToRevokedList} size="sm" variant="outline">
          Ir a la lista de revocados
          <ArrowRight />
        </Button>
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceRevokedDialog;
