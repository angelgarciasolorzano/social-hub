import { Fragment } from "react";

import { Form } from "@inertiajs/react";

import dayjs from "dayjs";
import "dayjs/locale/es";
import relativeTime from "dayjs/plugin/relativeTime";
import { AlertTriangleIcon, Clock4, MonitorSmartphone } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

import {
  destroyAll as destroyAllRoute,
  destroy as destroyRoute,
} from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Separator } from "@/shared/components/shadcn/ui/separator";

dayjs.extend(relativeTime);
dayjs.locale("es");

interface TwoFactorDevicesProps {
  devices: TrustedDevice[];
}

function deviceLabel(device: TrustedDevice): string {
  return device.name ?? device.userAgent ?? "Dispositivo desconocido";
}

function longDate(iso: string): string {
  return dayjs(iso).format("D [de] MMMM [del] YYYY");
}

function fromNow(iso: string | null): string {
  if (iso === null) {
    return "nunca";
  }

  return dayjs(iso).fromNow();
}

function TwoFactorDevices({ devices }: TwoFactorDevicesProps) {
  const hasDevices = devices.length > 0;
  const mostRecent = devices[0];
  const lastUsedAt = mostRecent?.lastUsedAt ?? null;

  return (
    <>
      <Alert className="max-w-md border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-500">
        <AlertTriangleIcon />

        <AlertTitle>
          Los dispositivos de confianza reducen la frecuencia con la que se te solicita el código de
          verificación.
        </AlertTitle>

        <AlertDescription>
          Revoca cualquier dispositivo que ya no utilices para mantener tu cuenta segura.
        </AlertDescription>
      </Alert>

      <div className="flex flex-col gap-3 rounded-md border p-5">
        {hasDevices ? (
          <>
            {devices.map((device, index) => (
              <Fragment key={device.id}>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="truncate text-sm font-medium" title={deviceLabel(device)}>
                      {deviceLabel(device)}
                    </span>

                    {device.ip !== null && (
                      <span className="truncate text-xs text-muted-foreground">
                        IP: {device.ip}
                      </span>
                    )}

                    <span className="text-xs text-muted-foreground">
                      Último uso: {fromNow(device.lastUsedAt)}
                    </span>

                    <span className="text-xs text-muted-foreground">
                      Expira: {longDate(device.expiresAt)}
                    </span>
                  </div>

                  <Form {...destroyRoute.delete({ trustedDevice: device.id })} className="shrink-0">
                    {() => (
                      <Button type="submit" variant="ghost" size="sm">
                        Revocar
                      </Button>
                    )}
                  </Form>
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

      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">Dispositivos registrados</span>

        <span className="font-medium">{devices.length}</span>
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

      <Item variant="outline">
        <ItemContent>
          <ItemTitle>Última actividad</ItemTitle>

          <ItemDescription>{fromNow(lastUsedAt)}</ItemDescription>
        </ItemContent>

        <ItemActions>
          <Clock4 size={20} className="text-muted-foreground" />
        </ItemActions>
      </Item>
    </>
  );
}

export default TwoFactorDevices;
