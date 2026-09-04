import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";

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
  Pencil,
  RefreshCcw,
  RotateCw,
  ShieldOff,
  Trash2,
} from "lucide-react";

import ActivityTimeline, {
  type ActivityStep,
} from "@/modules/setting/modules/trustedDevices/components/ui/ActivityTimeline";
import DeviceMetadataItem, {
  type DeviceMetadataItemProps,
} from "@/modules/setting/modules/trustedDevices/components/ui/DeviceMetadataItem";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevices/utils/valueOrFallback";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

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

import type { Appearance } from "@/shared/hooks";
import { useAppearance, useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import {
  alertVariants,
  badgeVariants,
  buttonVariants,
  iconColorVariants,
} from "@/shared/lib/styling";

import RenameDeviceDialog from "./RenameDeviceDialog";
import RenewTrustDialog from "./RenewTrustDialog";
import TrustedDeviceForceDestroyDialog from "./TrustedDeviceForceDestroyDialog";
import TrustedDeviceReactivationDialog from "./TrustedDeviceReactivationDialog";
import TrustedDeviceRevokeDialog from "./TrustedDeviceRevokeDialog";

interface DeviceDetailsDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

type DeviceDetailsDialogAction =
  "renameDevice" | "renewTrust" | "revokeDevice" | "reactivate" | "forceDestroy";

type DialogActionState = Pick<DeviceDetailsDialogProps, "device"> &
  DialogClosingState & {
    kind: DeviceDetailsDialogAction;
  };

function DeviceDetailsDialog({ device, open, onClose }: DeviceDetailsDialogProps): JSX.Element {
  const dialogDevice = useDialog<DialogActionState | null>(null);
  const { appearance } = useAppearance();

  const isRevoked = device.deletedAt !== null;

  const handleDeviceAction = (action: DeviceDetailsDialogAction, device: TrustedDevice): void => {
    dialogDevice.show({ kind: action, device, closing: false });
  };

  const handleDialogClose = createDialogCloseHandler(dialogDevice);

  const renderDialogDevice = (): JSX.Element | null => {
    if (dialogDevice.state === null) {
      return null;
    }

    const isClosing = dialogDevice.state.closing;
    const actionDevice = dialogDevice.state.device;

    switch (dialogDevice.state.kind) {
      case "renameDevice":
        return (
          <RenameDeviceDialog device={actionDevice} open={!isClosing} onClose={handleDialogClose} />
        );

      case "renewTrust":
        return (
          <RenewTrustDialog device={actionDevice} open={!isClosing} onClose={handleDialogClose} />
        );

      case "revokeDevice":
        return (
          <TrustedDeviceRevokeDialog
            device={actionDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case "reactivate":
        return (
          <TrustedDeviceReactivationDialog
            device={actionDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case "forceDestroy":
        return (
          <TrustedDeviceForceDestroyDialog
            device={actionDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      default:
        return null;
    }
  };

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
              {isRevoked ? (
                <Badge
                  variant={appearance === "light" ? "destructive" : null}
                  className="dark:bg-red-700 dark:text-white"
                >
                  Revocado
                </Badge>
              ) : (
                <Badge className={badgeVariants.success}>Activo</Badge>
              )}
            </div>
          </DialogTitle>
          <DialogDescription>
            {isRevoked
              ? "Consulta la informacion del dispositivo que fue revocado de tu cuenta."
              : "Consulta la información completa de este dispositivo de confianza."}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <DeviceOverviewCard device={device} isRevoked={isRevoked} appearance={appearance} />

          <div className="grid grid-cols-[1.8fr_1.7fr_2fr] gap-4">
            <DeviceActivityCard device={device} />

            <DeviceMetadataCard device={device} />

            <DeviceStatusCallouts
              isRevoked={isRevoked}
              onReactivate={() => {
                handleDeviceAction("reactivate", device);
              }}
            />
          </div>
        </div>

        <DialogFooter className="flex items-center sm:justify-between">
          {isRevoked ? (
            <RevokedFooterActions device={device} onAction={handleDeviceAction} onClose={onClose} />
          ) : (
            <ActiveFooterActions device={device} onAction={handleDeviceAction} onClose={onClose} />
          )}
        </DialogFooter>

        {renderDialogDevice()}
      </DialogContent>
    </Dialog>
  );
}

interface DeviceOverviewCardProps {
  device: TrustedDevice;
  isRevoked: boolean;
  appearance: Appearance;
}

function DeviceOverviewCard({
  device,
  isRevoked,
  appearance,
}: DeviceOverviewCardProps): JSX.Element {
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
            {getDeviceIcon(device, cn("h-12 w-12", iconColorVariants.violet.iconFgClass))}
          </div>

          <div className="flex flex-col items-start gap-3">
            <div className="flex items-center justify-center gap-2">
              <span className="max-w-90 truncate text-2xl font-semibold">{device.name}</span>

              {isRevoked ? (
                <Badge
                  variant={appearance === "light" ? "destructive" : null}
                  className="dark:bg-red-700 dark:text-white"
                >
                  <ShieldOff className="size-3" data-icon="inline-start" />
                  Revocado
                </Badge>
              ) : (
                <Badge className={badgeVariants.success}>
                  <Circle
                    className="size-1.5! fill-green-800 text-green-800 dark:fill-green-500 dark:text-green-500"
                    data-icon="inline-start"
                  />
                  Activo
                </Badge>
              )}
            </div>

            <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <div className="flex items-center gap-1.5">
                {getDeviceIcon(device, "h-4 w-4")}
                <span>{device.osName}</span>
              </div>

              <FaCircle className="h-1 w-1" />

              <span>{device.browser}</span>

              <FaCircle className="h-1 w-1" />

              <span>{isRevoked ? "Dispositivo revocado" : "Dispositivo de confianza"}</span>
            </div>

            <p className="text-sm text-muted-foreground">
              {isRevoked
                ? "Este dispositivo fue removido de tu lista de dispositivos de confianza. La proxima vez que inicies sesion desde el, se te solicitara el codigo de verificacion."
                : "Este dispositivo ha sido verificado y agregado a tu lista de dispositivos de confianza. No se te solicitara el codigo de verificacion cada vez que inicies sesion desde este dispositivo hasta su fecha de expiracion."}
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
              {isRevoked
                ? "Aunque la fecha de expiracion siga vigente, este dispositivo ya no es de confianza. Reactivalo si quieres volver a confiar en el."
                : "Cuando expire, se te volvera a solicitar el codigo de verificacion al iniciar sesion desde este dispositivo."}
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
  key: "browser" | "browserVersion" | "os" | "ip";
};

function DeviceMetadataCard({ device }: DeviceMetadataCardProps): JSX.Element {
  const browser = valueOrFallback(device.browser, "Desconocido");
  const browserVersion = valueOrFallback(device.browserVersion, "Desconocida");
  const osName = valueOrFallback(device.osName, "Desconocido");
  const ip = valueOrFallback(device.ip, "No disponible");

  const items: DeviceMetadataItems[] = [
    {
      key: "browser",
      icon: <Globe className={cn("h-6 w-6", iconColorVariants.violet.iconFgClass)} />,
      title: "Navegador",
      description: browser,
    },
    {
      key: "browserVersion",
      icon: <Globe className={cn("h-6 w-6", iconColorVariants.violet.iconFgClass)} />,
      title: "Version del navegador",
      description: browserVersion,
    },
    {
      key: "os",
      icon: getDeviceIcon(device, cn("h-6 w-6", iconColorVariants.violet.iconFgClass)),
      title: "Sistema operativo",
      description: osName,
    },
    {
      key: "ip",
      icon: <MapPin className={cn("h-6 w-6", iconColorVariants.violet.iconFgClass)} />,
      title: "Direccion IP",
      description: ip,
    },
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

interface DeviceStatusCalloutsProps {
  isRevoked: boolean;
  onReactivate: () => void;
}

function DeviceStatusCallouts({ isRevoked, onReactivate }: DeviceStatusCalloutsProps): JSX.Element {
  if (isRevoked) {
    return (
      <div className="flex flex-col gap-3">
        <Alert className={alertVariants.warning}>
          <ShieldOff />

          <AlertTitle>Este dispositivo está revocado</AlertTitle>
          <AlertDescription>
            Ya no se considera de confianza. La proxima vez que inicies sesion desde el, se te
            solicitara el codigo de verificacion.
            <Separator className="my-1" />
            <Button className="cursor-pointer" onClick={onReactivate} size="sm" variant="outline">
              <RotateCw data-icon="inline-start" />
              Reactivar ahora
            </Button>
          </AlertDescription>
        </Alert>

        <Alert className={cn(alertVariants.preview, "dark:text-purple-400")}>
          <Lightbulb />

          <AlertTitle>¿Que significa revocar un dispositivo?</AlertTitle>
          <AlertDescription>
            Revocar un dispositivo lo retira de tu lista de confianza y borra su token de acceso. La
            proxima vez que inicies sesion desde el, deberas verificarte con un codigo OTP. Si fue
            un error o quieres volver a confiar en el, puedes reactivarlo y se generara un token
            nuevo por seguridad.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

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

interface FooterActionsProps {
  device: TrustedDevice;
  onAction: (action: DeviceDetailsDialogAction, device: TrustedDevice) => void;
  onClose: () => void;
}

function ActiveFooterActions({ device, onAction, onClose }: FooterActionsProps): JSX.Element {
  return (
    <>
      <div className="flex items-center gap-2">
        <Button
          className="cursor-pointer"
          onClick={() => {
            onAction("renameDevice", device);
          }}
          type="button"
          variant="outline"
        >
          <Pencil data-icon="inline-start" />
          Renombrar dispositivo
        </Button>

        <Button
          className="cursor-pointer"
          onClick={() => {
            onAction("renewTrust", device);
          }}
          type="button"
          variant="outline"
        >
          <RefreshCcw data-icon="inline-start" />
          Renovar confianza
        </Button>
      </div>

      <div className="flex items-center gap-2">
        <Button className="cursor-pointer" onClick={onClose} type="button" variant="outline">
          Cerrar
        </Button>

        <Button
          className={buttonVariants.destructive}
          onClick={() => {
            onAction("revokeDevice", device);
          }}
          variant="destructive"
        >
          <Trash2 />
          Revokar dispositivo
        </Button>
      </div>
    </>
  );
}

function RevokedFooterActions({ device, onAction, onClose }: FooterActionsProps): JSX.Element {
  return (
    <>
      <div className="flex items-center gap-2">
        <Button
          className="cursor-pointer"
          onClick={() => {
            onAction("reactivate", device);
          }}
          type="button"
          variant="outline"
        >
          <RotateCw data-icon="inline-start" />
          Reactivar dispositivo
        </Button>

        <Button
          className="cursor-pointer"
          onClick={() => {
            onAction("forceDestroy", device);
          }}
          type="button"
          variant="outline"
        >
          <Trash2 data-icon="inline-start" />
          Eliminar definitivamente
        </Button>
      </div>

      <div className="flex items-center gap-2">
        <Button className="cursor-pointer" onClick={onClose} type="button" variant="outline">
          Cerrar
        </Button>
      </div>
    </>
  );
}

export default DeviceDetailsDialog;
