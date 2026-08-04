import type { JSX } from "react";

import dayjs from "dayjs";
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

        <div className="flex flex-col gap-1">
          <span className="font-semibold">Detalles del dispositivo registrado</span>

          <p className="text-sm text-muted-foreground">
            Asi es como identificamos este dispositivo actualmente.
          </p>
        </div>

        <DeviceInfoCard existingDevice={existingDevice} />

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

        <DialogFooter>
          <Button type="button" onClick={onClose}>
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DeviceInfoCardProps = Pick<DeviceAlreadyRegisteredDialogProps, "existingDevice">;

function DeviceInfoCard({ existingDevice }: DeviceInfoCardProps): JSX.Element {
  const browser = existingDevice.browser !== "" ? existingDevice.browser : "Desconocido";
  const osName = existingDevice.osName !== "" ? existingDevice.osName : "Desconocido";
  const ip = existingDevice.ip ?? "No disponible";

  return (
    <div className="flex flex-col gap-8 rounded-xl border bg-card p-6 shadow-sm dark:bg-input/10">
      <div className="grid grid-cols-[2fr_auto_2fr_auto_2fr] items-stretch gap-8">
        <div className="flex gap-4">
          <div className="flex h-10 w-10 rounded-md bg-blue-100/50 p-2 dark:bg-blue-900/20">
            <Globe className="h-6 w-6 text-blue-700 dark:text-blue-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Navegador</span>
            <span className="text-sm text-muted-foreground">{browser}</span>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div className="flex gap-4">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <Monitor className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Sistema operativo</span>
            <span className="text-sm text-muted-foreground">{osName}</span>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div className="flex gap-4">
          <div className="flex h-10 w-10 rounded-md bg-orange-100/50 p-2 dark:bg-orange-900/20">
            <MapPin className="h-6 w-6 text-orange-700 dark:text-orange-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Direccion IP</span>
            <span className="text-sm text-muted-foreground">{ip}</span>
          </div>
        </div>
      </div>

      <Separator />

      <div className="grid grid-cols-[2fr_auto_2fr] gap-8">
        <div className="flex gap-4">
          <div className="flex h-10 w-10 rounded-md bg-cyan-100/50 p-2 dark:bg-cyan-900/20">
            <Clock4 className="h-6 w-6 text-cyan-700 dark:text-cyan-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Ultimo acceso</span>

            <Badge className="mt-1.5 block bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300">
              {dayjs(existingDevice.lastUsedAt).fromNow()}
            </Badge>

            <span className="text-sm text-muted-foreground">
              {formatLongDate(existingDevice.lastUsedAt)}
            </span>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div className="flex gap-4">
          <div className="flex h-10 w-10 rounded-md bg-green-100/50 p-2 dark:bg-green-900/20">
            <MapPin className="h-6 w-6 text-green-700 dark:text-green-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Expira el</span>

            <span className="text-sm text-muted-foreground">
              {formatLongDate(existingDevice.expiresAt)}
            </span>

            <Badge className="mt-1.5 block bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300">
              {formatTimeUntil(existingDevice.expiresAt)}
            </Badge>
          </div>
        </div>
      </div>
    </div>
  );
}

export default DeviceAlreadyRegisteredDialog;
