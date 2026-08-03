import type { JSX } from "react";

import dayjs from "dayjs";
import { CircleCheck, Globe, MapPin, Monitor, ShieldCheck } from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import type { DevicePreview } from "../../../types/devicePreview";
import type { TrustedDevice } from "../../../types/trustedDevice";

interface DeviceAlreadyRegisteredDialogProps {
  preview: DevicePreview | null;
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

function DeviceAlreadyRegisteredDialog({
  preview,
  existingDevice,
  open,
  onClose,
}: DeviceAlreadyRegisteredDialogProps): JSX.Element {
  const deviceName = existingDevice.name ?? "este dispositivo";

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          onClose();
        }
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5 text-muted-foreground" />
              Este dispositivo ya esta registrado
            </div>
          </DialogTitle>
          <DialogDescription>
            Tu dispositivo actual ya forma parte de tu lista de dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
            <CircleCheck data-icon="inline-start" />

            <AlertTitle>{`Registrado como "${deviceName}"`}</AlertTitle>

            <AlertDescription>
              Agregado el {formatLongDate(existingDevice.createdAt)} · vence el{" "}
              {formatLongDate(existingDevice.expiresAt)}
            </AlertDescription>
          </Alert>

          <DevicePreviewInfo preview={preview} />
        </div>

        <DialogFooter>
          <Button type="button" onClick={onClose}>
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DevicePreviewInfoProps = Pick<DeviceAlreadyRegisteredDialogProps, "preview">;

function DevicePreviewInfo({ preview }: DevicePreviewInfoProps): JSX.Element {
  const browser =
    preview?.browser !== "" && preview?.browser !== undefined ? preview.browser : "Desconocido";

  const osName =
    preview?.osName !== "" && preview?.osName !== undefined ? preview.osName : "Desconocido";

  const ip = preview?.ip ?? "No disponible";

  return (
    <Card>
      <CardHeader>
        <CardTitle>Informacion del dispositivo</CardTitle>
        <CardDescription>Tu dispositivo actual tiene la siguiente informacion:</CardDescription>
      </CardHeader>

      <CardContent className="flex items-stretch gap-4">
        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <Globe className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Navegador</span>
            <span className="text-sm text-muted-foreground">{browser}</span>
          </div>
        </div>

        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <Monitor className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Sistema operativo</span>
            <span className="text-sm text-muted-foreground">{osName}</span>
          </div>
        </div>

        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <MapPin className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Direccion IP</span>
            <span className="text-sm text-muted-foreground">{ip}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export default DeviceAlreadyRegisteredDialog;
