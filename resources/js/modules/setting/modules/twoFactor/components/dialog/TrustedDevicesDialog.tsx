import { Form } from "@inertiajs/react";

import dayjs from "dayjs";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

import {
  destroyAll as destroyAllRoute,
  destroy as destroyRoute,
} from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardAction,
  CardContent,
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

interface Props {
  devices: TrustedDevice[];
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function TrustedDevicesDialog({ devices, open, onOpenChange }: Props) {
  const hasDevices = devices.length > 0;

  const deviceLabel = (device: TrustedDevice): string => {
    return device.name ?? device.userAgent ?? "Dispositivo desconocido";
  };

  const longDate = (iso: string): string => {
    return dayjs(iso).format("D [de] MMMM [del] YYYY");
  };

  const fromNow = (iso: string | null): string => {
    if (iso === null) {
      return "nunca";
    }

    return dayjs(iso).fromNow();
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Dispositivos de confianza</DialogTitle>

          <DialogDescription>
            Estos navegadores pueden iniciar sesión sin pedirte el código 2FA durante 30 días.
            Revoca uno si dejaste de usarlo.
          </DialogDescription>
        </DialogHeader>

        {hasDevices ? (
          <ul className="divide-y">
            {devices.map((device) => (
              <Card key={device.id}>
                <CardHeader>
                  <CardTitle>{deviceLabel(device)}</CardTitle>
                  <CardAction>
                    <Form
                      {...destroyRoute.delete({ trustedDevice: device.id })}
                      className="shrink-0"
                    >
                      {() => (
                        <Button type="submit" size="sm">
                          Revocar
                        </Button>
                      )}
                    </Form>
                  </CardAction>
                </CardHeader>

                <CardContent className="space-y-2">
                  {device.ip !== null && (
                    <p className="truncate text-xs text-muted-foreground">IP: {device.ip}</p>
                  )}

                  <p className="text-xs text-muted-foreground">
                    Último uso: {fromNow(device.lastUsedAt)}
                  </p>

                  <p className="text-xs text-muted-foreground">
                    Expira: {longDate(device.expiresAt)}
                  </p>
                </CardContent>
              </Card>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted-foreground">
            No tienes dispositivos de confianza configurados.
          </p>
        )}

        {hasDevices && (
          <DialogFooter>
            <Form {...destroyAllRoute.delete()}>
              {() => (
                <Button type="submit" variant="destructive">
                  Revocar todos
                </Button>
              )}
            </Form>
          </DialogFooter>
        )}
      </DialogContent>
    </Dialog>
  );
}

export default TrustedDevicesDialog;
