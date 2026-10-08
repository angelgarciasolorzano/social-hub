import { type JSX, useRef } from "react";

import { FaCircle } from "react-icons/fa";

import {
  CalendarClock,
  CalendarPlus,
  ChevronRight,
  Circle,
  CircleAlert,
  CircleCheck,
  Clock,
  Earth,
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

import TrustedDeviceActivityTimeline, {
  type ActivityStep,
} from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceActivityTimeline";
import TrustedDeviceMetadataItem, {
  type TrustedDeviceMetadataItemProps,
} from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceMetadataItem";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";
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

import TrustedDeviceForceDestroyDialog from "./TrustedDeviceForceDestroyDialog";
import TrustedDeviceReactivationDialog from "./TrustedDeviceReactivationDialog";
import TrustedDeviceRenameDialog from "./TrustedDeviceRenameDialog";
import TrustedDeviceRenewTrustDialog from "./TrustedDeviceRenewTrustDialog";
import TrustedDeviceRevokeDialog from "./TrustedDeviceRevokeDialog";

interface TrustedDeviceDetailsDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
  returnFocusTarget?: HTMLElement | null;
}

type TrustedDeviceDetailsDialogAction =
  "renameDevice" | "renewTrust" | "revokeDevice" | "reactivate" | "forceDestroy";

type DialogActionState = Pick<TrustedDeviceDetailsDialogProps, "device"> &
  DialogClosingState & {
    kind: TrustedDeviceDetailsDialogAction;
    returnFocusTarget: HTMLElement | null;
  };

function TrustedDeviceDetailsDialog({
  device,
  open,
  onClose,
  returnFocusTarget,
}: TrustedDeviceDetailsDialogProps): JSX.Element {
  const dialogDevice = useDialog<DialogActionState | null>(null);
  const { appearance } = useAppearance();
  const titleRef = useRef<HTMLHeadingElement>(null);
  const { onOpenAutoFocus, onCloseAutoFocus } = useDialogFocusRestoration({
    captureActiveElement: false,
    getFallbackFocusTarget: () => returnFocusTarget,
    onOpenAutoFocus: (event) => {
      event.preventDefault();
      titleRef.current?.focus();
    },
  });

  const isRevoked = device.deletedAt !== null;
  const isExpired = !isRevoked && !device.isActive;

  const handleDeviceAction = (
    action: TrustedDeviceDetailsDialogAction,
    device: TrustedDevice,
  ): void => {
    const activeElement = document.activeElement;

    dialogDevice.show({
      kind: action,
      device,
      returnFocusTarget:
        activeElement instanceof HTMLElement && activeElement !== document.body
          ? activeElement
          : null,
      closing: false,
    });
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
          <TrustedDeviceRenameDialog
            device={actionDevice}
            open={!isClosing}
            onClose={handleDialogClose}
            returnFocusTarget={dialogDevice.state.returnFocusTarget}
          />
        );

      case "renewTrust":
        return (
          <TrustedDeviceRenewTrustDialog
            device={actionDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
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
      <DialogContent
        className="grid max-h-[calc(100dvh-2rem)] min-h-0 w-[calc(100vw-2rem)] max-w-6xl min-w-0 grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden sm:max-w-6xl"
        onOpenAutoFocus={onOpenAutoFocus}
        onCloseAutoFocus={onCloseAutoFocus}
      >
        <DialogHeader>
          <DialogTitle asChild>
            <h2
              className="flex flex-wrap items-center gap-2 pr-8 text-lg font-semibold outline-none"
              ref={titleRef}
              tabIndex={-1}
            >
              <Eye aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Detalles del dispositivo
              {isRevoked ? (
                <Badge
                  variant={appearance === "light" ? "destructive" : null}
                  className="dark:bg-red-700 dark:text-white"
                >
                  Revocado
                </Badge>
              ) : isExpired ? (
                <Badge className={badgeVariants.warning}>Expirado</Badge>
              ) : (
                <Badge className={badgeVariants.success}>Activo</Badge>
              )}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {isRevoked
              ? "Consulta la informacion del dispositivo que fue revocado de tu cuenta."
              : isExpired
                ? "Consulta la información de un dispositivo cuya confianza ya expiró."
                : "Consulta la información completa de este dispositivo de confianza."}
          </DialogDescription>
        </DialogHeader>

        <div
          aria-label="Información del dispositivo"
          className="min-h-0 overflow-y-auto overscroll-contain pr-2 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1"
          role="region"
          tabIndex={0}
        >
          <div className="flex min-w-0 flex-col gap-4 pb-1">
            <DeviceOverviewCard
              device={device}
              isExpired={isExpired}
              isRevoked={isRevoked}
              appearance={appearance}
            />

            <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1.7fr)_minmax(0,2fr)]">
              <DeviceActivityCard device={device} />

              <DeviceMetadataCard device={device} />

              <div className="md:col-span-2 xl:col-span-1">
                <DeviceStatusCallouts
                  isExpired={isExpired}
                  isRevoked={isRevoked}
                  onReactivate={() => {
                    handleDeviceAction("reactivate", device);
                  }}
                />
              </div>
            </div>
          </div>
        </div>

        <DialogFooter className="flex flex-col gap-2 border-t pt-4 md:flex-row md:items-center md:justify-between">
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
  isExpired: boolean;
  isRevoked: boolean;
  appearance: Appearance;
}

function DeviceOverviewCard({
  device,
  isExpired,
  isRevoked,
  appearance,
}: DeviceOverviewCardProps): JSX.Element {
  return (
    <Card className="min-w-0 dark:bg-input/20">
      <CardContent className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-[minmax(0,1.3fr)_auto_minmax(0,1fr)] md:gap-6">
        <div className="flex min-w-0 flex-col gap-4 sm:flex-row">
          <div
            className={cn(
              iconColorVariants.violet.iconBgClass,
              "flex h-20 w-20 shrink-0 rounded-md border border-violet-100 p-4 dark:border-violet-200/10",
            )}
          >
            {getDeviceIcon(device, cn("h-12 w-12", iconColorVariants.violet.iconFgClass))}
          </div>

          <div className="flex min-w-0 flex-1 flex-col items-start gap-3">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <h3 className="max-w-full min-w-0 text-xl font-semibold break-words">
                {valueOrFallback(device.name, "Dispositivo sin nombre")}
              </h3>

              {isRevoked ? (
                <Badge
                  variant={appearance === "light" ? "destructive" : null}
                  className="dark:bg-red-700 dark:text-white"
                >
                  <ShieldOff className="size-3" data-icon="inline-start" />
                  Revocado
                </Badge>
              ) : isExpired ? (
                <Badge className={badgeVariants.warning}>
                  <CalendarClock className="size-3" data-icon="inline-start" />
                  Expirado
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

            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
              <div className="flex items-center gap-1.5">
                {getDeviceIcon(device, "h-4 w-4")}
                <span>{valueOrFallback(device.osName, "Desconocido")}</span>
              </div>

              <FaCircle className="h-1 w-1" />

              <span>{valueOrFallback(device.browser, "Desconocido")}</span>

              <FaCircle className="h-1 w-1" />

              <span>
                {isRevoked
                  ? "Dispositivo revocado"
                  : isExpired
                    ? "Confianza expirada"
                    : "Dispositivo de confianza"}
              </span>
            </div>

            <p className="text-sm text-muted-foreground">
              {isRevoked
                ? "Este dispositivo fue removido de tu lista de dispositivos de confianza. La proxima vez que inicies sesion desde el, se te solicitara el codigo de verificacion."
                : isExpired
                  ? "El periodo de confianza de este dispositivo expiró. Renueva la confianza para volver a omitir la verificación de dos factores."
                  : "Este dispositivo ha sido verificado y agregado a tu lista de dispositivos de confianza. No se te solicitara el codigo de verificacion cada vez que inicies sesion desde este dispositivo hasta su fecha de expiracion."}
            </p>
          </div>
        </div>

        <Separator className="hidden md:block" orientation="vertical" />

        <div className="min-w-0">
          <Item>
            <ItemMedia>
              <CalendarClock />
            </ItemMedia>
            <ItemContent>
              <ItemTitle className="text-xs font-medium">Fecha de expiración</ItemTitle>
              <ItemDescription>
                <span className="text-sm font-normal">{formatLongDate(device.expiresAt)}</span>

                <Badge className={cn(badgeVariants.warning, "mt-1.5 block text-sm font-normal")}>
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
                : isExpired
                  ? "El periodo de confianza ya expiró. Se te solicitará el código de verificación al iniciar sesión desde este dispositivo."
                  : "Cuando expire, se te volvera a solicitar el codigo de verificacion al iniciar sesion desde este dispositivo."}
            </AlertTitle>
          </Alert>
        </div>
      </CardContent>
    </Card>
  );
}

type DeviceActivityCardProps = Pick<TrustedDeviceDetailsDialogProps, "device">;

function DeviceActivityCard({ device }: DeviceActivityCardProps): JSX.Element {
  const activitySteps: ActivityStep[] = [
    {
      icon: Clock,
      title: "Último acceso",
      meta: (
        <>
          <span className="block text-sm font-normal">{fromNow(device.lastUsedAt)}</span>
          <span className="block text-sm font-normal">{formatLongDate(device.lastUsedAt)}</span>
        </>
      ),
    },
    {
      icon: CalendarPlus,
      title: "Fecha de registro",
      meta: <span className="text-sm font-normal">{formatLongDate(device.createdAt)}</span>,
    },
    {
      icon: CalendarClock,
      title: "Fecha de expiración",
      meta: (
        <>
          <span className="block text-sm font-normal">{formatLongDate(device.expiresAt)}</span>
          <Badge className={cn(badgeVariants.warning, "mt-1.5 text-sm font-normal")}>
            {formatTimeUntil(device.expiresAt)}
          </Badge>
        </>
      ),
    },
  ];

  return (
    <Card className="min-w-0 dark:bg-input/20">
      <CardHeader>
        <h3 className="text-lg leading-none font-semibold">Actividad del dispositivo</h3>
      </CardHeader>
      <CardContent className="[&_li_h3]:text-xs [&_li_h3]:font-medium">
        <TrustedDeviceActivityTimeline steps={activitySteps} variant="violet" />
      </CardContent>
    </Card>
  );
}

type DeviceMetadataCardProps = Pick<TrustedDeviceDetailsDialogProps, "device">;

type DeviceMetadataItems = Pick<
  TrustedDeviceMetadataItemProps,
  "icon" | "title" | "description" | "iconColor"
> & {
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
      icon: <Globe className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />,
      title: "Navegador",
      description: browser,
      iconColor: "blue",
    },
    {
      key: "browserVersion",
      icon: <Earth className={cn("h-6 w-6", iconColorVariants.amber.iconFgClass)} />,
      title: "Version del navegador",
      description: browserVersion,
      iconColor: "amber",
    },
    {
      key: "os",
      icon: getDeviceIcon(device, cn("h-6 w-6", iconColorVariants.purple.iconFgClass)),
      title: "Sistema operativo",
      description: osName,
      iconColor: "purple",
    },
    {
      key: "ip",
      icon: <MapPin className={cn("h-6 w-6", iconColorVariants.green.iconFgClass)} />,
      title: "Direccion IP",
      description: ip,
      iconColor: "green",
    },
  ];

  return (
    <Card className="min-w-0 dark:bg-input/20">
      <CardHeader>
        <h3 className="text-lg leading-none font-semibold">Informacion del dispositivo</h3>
      </CardHeader>
      <CardContent className="flex min-w-0 flex-col gap-3">
        {items.map((item) => (
          <div
            className="flex min-w-0 gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/30 [&_dd]:break-words [&_dl]:min-w-0 [&_dl]:flex-1"
            key={item.key}
          >
            <TrustedDeviceMetadataItem
              icon={item.icon}
              title={item.title}
              description={item.description}
              iconColor={item.iconColor}
            />
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

interface DeviceStatusCalloutsProps {
  isExpired: boolean;
  isRevoked: boolean;
  onReactivate: () => void;
}

function DeviceStatusCallouts({
  isExpired,
  isRevoked,
  onReactivate,
}: DeviceStatusCalloutsProps): JSX.Element {
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

  if (isExpired) {
    return (
      <div className="flex flex-col gap-3">
        <Alert className={alertVariants.warning}>
          <CalendarClock />

          <AlertTitle>La confianza de este dispositivo expiró</AlertTitle>
          <AlertDescription>
            Ya no puede omitir la verificación de dos factores. Renueva la confianza para volver a
            marcarlo como dispositivo de confianza.
          </AlertDescription>
        </Alert>

        <TrustedDeviceInformationCallout />
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
            <li>Puedes renovarlo en cualquier momento.</li>
          </ul>
        </AlertDescription>
      </Alert>

      <TrustedDeviceInformationCallout />
    </div>
  );
}

function TrustedDeviceInformationCallout(): JSX.Element {
  return (
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
  );
}

interface FooterActionsProps {
  device: TrustedDevice;
  onAction: (action: TrustedDeviceDetailsDialogAction, device: TrustedDevice) => void;
  onClose: () => void;
}

function ActiveFooterActions({ device, onAction, onClose }: FooterActionsProps): JSX.Element {
  return (
    <>
      <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center">
        <Button
          className="w-full cursor-pointer md:w-auto"
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
          className="w-full cursor-pointer md:w-auto"
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

      <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center">
        <Button
          className="w-full cursor-pointer md:w-auto"
          onClick={onClose}
          type="button"
          variant="outline"
        >
          Cerrar
        </Button>

        <Button
          className={cn(buttonVariants.destructive, "w-full md:w-auto")}
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
      <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center">
        <Button
          className="w-full cursor-pointer md:w-auto"
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
          className="w-full cursor-pointer md:w-auto"
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

      <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center">
        <Button
          className="w-full cursor-pointer md:w-auto"
          onClick={onClose}
          type="button"
          variant="outline"
        >
          Cerrar
        </Button>
      </div>
    </>
  );
}

export default TrustedDeviceDetailsDialog;
