import { Fragment } from "react";

import { Form } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";
import { MdOutlineLaptopMac } from "react-icons/md";

import dayjs from "dayjs";
import { AlertTriangleIcon, EllipsisVertical, MonitorSmartphone, Plus } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

import { destroyAll as destroyAllRoute } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Popover, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { Separator } from "@/shared/components/shadcn/ui/separator";

interface TwoFactorDevicesProps {
  devices: TrustedDevice[];
}

function TwoFactorDevices({ devices }: TwoFactorDevicesProps) {
  const hasDevices = devices.length > 0;

  const deviceLabel = (device: TrustedDevice): string => {
    return device.name ?? device.userAgent ?? "Dispositivo desconocido";
  };

  const fromNow = (iso: string | null): string => {
    if (iso === null) {
      return "nunca";
    }

    return dayjs(iso).fromNow();
  };

  return (
    <>
      <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
        <AlertTriangleIcon />

        <AlertTitle className="line-clamp-4">
          Los dispositivos de confianza reducen la frecuencia con la que se te solicita el código de
          verificación.
        </AlertTitle>

        <AlertDescription>
          Revoca cualquier dispositivo que ya no utilices para mantener tu cuenta segura.
        </AlertDescription>
      </Alert>

      <div className="flex items-center justify-between gap-4 font-semibold">
        <span className="text-sm">Dispositivos registrados</span>

        <Button variant="outline">
          <Plus data-icon="inline-end" />
          Agregar dispositivo
        </Button>
      </div>

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {hasDevices ? (
          <>
            {devices.map((device, index) => (
              <Fragment key={device.id}>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex min-w-0 flex-1 items-start gap-2.5">
                    <div className="flex h-8 w-8 rounded-md bg-muted p-1 dark:bg-primary-foreground">
                      <MdOutlineLaptopMac className="h-6 w-6 text-gray-600 dark:text-gray-400" />
                    </div>

                    <div className="flex flex-col gap-0.5">
                      <span className="truncate text-sm font-medium" title={deviceLabel(device)}>
                        {deviceLabel(device)}
                      </span>

                      {device.browser !== null && device.browser !== "" && (
                        <span className="truncate text-xs text-muted-foreground">
                          {device.osName} - {device.browser}
                        </span>
                      )}

                      <span className="text-xs text-muted-foreground">
                        Último uso: {fromNow(device.lastUsedAt)}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-4">
                    <FaCircle className="h-3 w-3 text-red-600 dark:text-red-500" />

                    <Popover>
                      <PopoverTrigger asChild>
                        <Button variant="outline" size="icon">
                          <EllipsisVertical />
                        </Button>
                      </PopoverTrigger>
                    </Popover>
                  </div>
                </div>

                {index < devices.length - 1 && <Separator />}
              </Fragment>
            ))}
          </>
        ) : (
          <div className="text-sm text-muted-foreground">
            No tienes dispositivos de confianza configurados.
          </div>
        )}
      </div>

      <Form {...destroyAllRoute.delete()}>
        {() => (
          <Button
            type="submit"
            variant="destructive"
            className="w-full py-6"
            disabled={!hasDevices}
          >
            <MonitorSmartphone className="mr-2 h-4 w-4" />
            Revocar todos
          </Button>
        )}
      </Form>

      <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
        <AlertTitle>Consejos</AlertTitle>

        <AlertDescription>
          <ul className="list-inside list-disc space-y-2">
            <li>Usa dispositivos que sean solo tuyos.</li>
            <li>Cierra sesión en dispositivos que ya no uses.</li>
            <li>Los cambios pueden tardar unos minutos en reflejarse.</li>
          </ul>
        </AlertDescription>
      </Alert>
    </>
  );
}

export default TwoFactorDevices;
