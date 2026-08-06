import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";
import { IoLogoApple } from "react-icons/io";
import { MdOutlineLaptopMac } from "react-icons/md";

import {
  CalendarClock,
  CalendarPlus,
  ChevronRight,
  Circle,
  CircleAlert,
  CircleCheck,
  Clock,
  Eye,
  Globe,
  Lightbulb,
  MapPin,
  MonitorSmartphone,
  Pencil,
  RefreshCcw,
  Trash2,
} from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/shadcn/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import {
  Item,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";
import { alertVariants, badgeVariants, iconColorVariants } from "@/shared/lib/styling";

import type { TrustedDevice } from "../../../types/trustedDevice";
import { formatLongDate, formatTimeUntil, fromNow } from "../../../utils/dateTime";
import { valueOrFallback } from "../../../utils/valueOrFallback";
import ActivityTimeline, { type ActivityStep } from "../../ui/ActivityTimeline";
import DeviceMetadataItem, { type DeviceMetadataItemProps } from "../../ui/DeviceMetadataItem";

interface DeviceDetailsDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

function DeviceDetailsDialog({ device, open, onClose }: DeviceDetailsDialogProps): JSX.Element {
  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) onClose();
      }}
    >
      <DialogContent className="max-w-6xl min-w-5xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Eye className="h-5 w-5 text-muted-foreground" />
              Detalles del dispositivo
              <Badge className={badgeVariants.success}>Activo</Badge>
            </div>
          </DialogTitle>
          <DialogDescription>
            Consulta la información completa de este dispositivo de confianza.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <DeviceOverviewCard device={device} />

          <div className="grid grid-cols-[1.8fr_1.7fr_2fr] gap-4">
            <DeviceActivityCard device={device} />

            <DeviceMetadataCard device={device} />

            <DeviceStatusCallouts />
          </div>
        </div>

        <DialogFooter className="flex items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <Button type="button" className="cursor-pointer" variant="outline">
              <Pencil data-icon="inline-start" />
              Renombrar dispositivo
            </Button>

            <Button type="button" className="cursor-pointer" variant="outline">
              <RefreshCcw data-icon="inline-start" />
              Renovar confianza
            </Button>
          </div>

          <div className="flex items-center gap-2">
            <Button type="button" className="cursor-pointer" variant="outline" onClick={onClose}>
              Cerrar
            </Button>

            <Button
              variant="destructive"
              className="dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
            >
              <Trash2 />
              Revokar dispositivo
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DeviceOverviewCardProps = Pick<DeviceDetailsDialogProps, "device">;

function DeviceOverviewCard({ device }: DeviceOverviewCardProps): JSX.Element {
  return (
    <Card className="dark:bg-input/10">
      <CardContent className="grid grid-cols-[1.3fr_auto_1fr] gap-6">
        <div className="flex gap-4">
          <div
            className={cn(
              iconColorVariants.violet.iconBgClass,
              "flex h-20 w-20 rounded-md border border-violet-100 p-4 dark:border-violet-200/10",
            )}
          >
            <MdOutlineLaptopMac className={cn("h-12 w-12", iconColorVariants.violet.iconFgClass)} />
          </div>

          <div className="flex flex-col items-start gap-3">
            <div className="flex items-center justify-center gap-2">
              <span className="max-w-90 truncate text-2xl font-semibold">{device.name}</span>

              <Badge className={badgeVariants.success}>
                <Circle
                  className="size-1.5! fill-green-800 text-green-800 dark:fill-green-500 dark:text-green-500"
                  data-icon="inline-start"
                />
                Activo
              </Badge>
            </div>

            <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <div className="flex items-center gap-1.5">
                <IoLogoApple className="h-4 w-4" />
                <span>{device.osName}</span>
              </div>

              <FaCircle className="h-1 w-1" />

              <span>{device.browser}</span>

              <FaCircle className="h-1 w-1" />

              <span>Dispositivo de confianza</span>
            </div>

            <p className="text-sm text-muted-foreground">
              Este dispositivo ha sido verificado y agregado a tu listab de dispositivos de
              confianza. No se te solicitara el codigo de verificacion cada vez que inicies sesion
              desde este dispositivo hasta su fecha de expiracion.
            </p>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div>
          <Item>
            <ItemMedia>
              <CalendarClock />
            </ItemMedia>
            <ItemContent>
              <ItemTitle>Fecha de expiración</ItemTitle>
              <ItemDescription>
                <span className="text-sm">{formatLongDate(device.expiresAt)}</span>

                <Badge className={cn(badgeVariants.warning, "mt-1.5 block")}>
                  {formatTimeUntil(device.expiresAt)}
                </Badge>
              </ItemDescription>
            </ItemContent>
          </Item>

          <Alert className={alertVariants.info}>
            <CircleAlert />

            <AlertTitle className="line-clamp-4">
              Cuando expire, se te volvera a solicitar el codigo de verificacion al iniciar sesion
              desde este dispositivo.
            </AlertTitle>
          </Alert>
        </div>
      </CardContent>
    </Card>
  );
}

type DeviceActivityCardProps = Pick<DeviceDetailsDialogProps, "device">;

function DeviceActivityCard({ device }: DeviceActivityCardProps): JSX.Element {
  const activitySteps: ActivityStep[] = [
    {
      icon: Clock,
      title: "Último acceso",
      meta: (
        <>
          <span className="block text-sm">{fromNow(device.lastUsedAt)}</span>
          <span className="block text-sm">{formatLongDate(device.lastUsedAt)}</span>
        </>
      ),
    },
    {
      icon: CalendarPlus,
      title: "Fecha de registro",
      meta: formatLongDate(device.createdAt),
    },
    {
      icon: CalendarClock,
      title: "Fecha de expiración",
      meta: (
        <>
          <span className="block text-sm">{formatLongDate(device.expiresAt)}</span>
          <Badge className={cn(badgeVariants.warning, "mt-1.5")}>
            {formatTimeUntil(device.expiresAt)}
          </Badge>
        </>
      ),
    },
  ];

  return (
    <Card className="dark:bg-input/10">
      <CardHeader>
        <CardTitle>Actividad del dispositivo</CardTitle>
      </CardHeader>
      <CardContent>
        <ActivityTimeline steps={activitySteps} variant="violet" />
      </CardContent>
    </Card>
  );
}

type DeviceMetadataCardProps = Pick<DeviceDetailsDialogProps, "device">;

type DeviceMetadataItems = Pick<DeviceMetadataItemProps, "icon" | "title" | "description"> & {
  key: "browser" | "os" | "ip";
};

function DeviceMetadataCard({ device }: DeviceMetadataCardProps): JSX.Element {
  const browser = valueOrFallback(device.browser, "Desconocido");
  const osName = valueOrFallback(device.osName, "Desconocido");
  const ip = valueOrFallback(device.ip, "No disponible");

  const items: DeviceMetadataItems[] = [
    { key: "browser", icon: Globe, title: "Navegador", description: browser },
    { key: "os", icon: MonitorSmartphone, title: "Sistema operativo", description: osName },
    { key: "ip", icon: MapPin, title: "Direccion IP", description: ip },
  ];

  return (
    <Card className="dark:bg-input/10">
      <CardHeader>
        <CardTitle>Informacion del dispositivo</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {items.map((item) => (
          <div
            className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/20"
            key={item.key}
          >
            <DeviceMetadataItem
              icon={item.icon}
              title={item.title}
              description={item.description}
            />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function DeviceStatusCallouts(): JSX.Element {
  return (
    <div className="flex flex-col gap-3">
      <Alert className={alertVariants.success}>
        <CircleCheck />

        <AlertTitle>Este dispositivo está activado</AlertTitle>
        <AlertDescription>
          No se te pedirá el código de verificación cada vez que inices sesión desde este
          dispositivo hasta su expiración.
          <Separator className="my-1" />
          <ul className="list-inside list-disc space-y-2">
            <li>Dispositivo verificado y de confianza.</li>
            <li>Acceso mas rapido y seguro.</li>
            <li>Puedes revocarlo en cualquier momento.</li>
          </ul>
        </AlertDescription>
      </Alert>

      <Alert className={cn(alertVariants.preview, "dark:text-purple-400")}>
        <Lightbulb />

        <AlertTitle>¿Que es un dispositivo de confianza?</AlertTitle>
        <AlertDescription>
          Los dispositivos de confianza reducen la frecuencia con la que se te solicita el codigo de
          verificacion al iniciar sesion, manteniendo tu cuenta segura.
          <span className="my-1 flex items-center justify-center gap-1 font-medium text-purple-800 hover:cursor-pointer hover:underline dark:text-purple-500">
            Mas información
            <ChevronRight className="h-5 w-5" />
          </span>
        </AlertDescription>
      </Alert>
    </div>
  );
}

export default DeviceDetailsDialog;
