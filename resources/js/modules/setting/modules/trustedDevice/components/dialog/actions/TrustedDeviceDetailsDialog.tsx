import { type JSX, useRef } from "react";

import { CircleAlert, CircleCheck, Eye } from "lucide-react";

import TrustedDeviceSummaryCard, {
  type TrustedDeviceSummaryStatus,
} from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

import { Alert, AlertDescription } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceDetailsDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
  returnFocusTarget?: HTMLElement | null;
}

function TrustedDeviceDetailsDialog({
  device,
  open,
  onClose,
  returnFocusTarget,
}: TrustedDeviceDetailsDialogProps): JSX.Element {
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
  const status: TrustedDeviceSummaryStatus = isRevoked
    ? "revoked"
    : isExpired
      ? "inactive"
      : "active";

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) onClose();
      }}
    >
      <DialogContent
        className="grid max-h-[calc(100dvh-2rem)] min-h-0 w-[calc(100vw-2rem)] max-w-3xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden"
        onOpenAutoFocus={onOpenAutoFocus}
        onCloseAutoFocus={onCloseAutoFocus}
      >
        <DialogHeader className="items-start text-left">
          <DialogTitle asChild>
            <h2
              className="flex w-full flex-wrap items-center justify-start gap-2 pr-8 text-left text-lg font-semibold outline-none"
              ref={titleRef}
              tabIndex={-1}
            >
              <Eye aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Detalles del dispositivo
            </h2>
          </DialogTitle>

          <DialogDescription className="text-left text-sm font-normal">
            {isRevoked
              ? "Consulta la información del dispositivo que fue revocado de tu cuenta."
              : isExpired
                ? "Consulta la información de un dispositivo cuya confianza ya expiró."
                : "Consulta la información completa de este dispositivo de confianza."}
          </DialogDescription>
        </DialogHeader>

        <div
          aria-label="Información del dispositivo"
          className="min-h-0 overflow-y-auto overscroll-contain outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1"
          role="region"
          tabIndex={0}
        >
          <div className="flex min-w-0 flex-col gap-4 pb-1">
            <TrustedDeviceSummaryCard
              device={device}
              lastUsedAt={fromNow(device.lastUsedAt)}
              expiration={formatLongDate(device.expiresAt)}
              status={status}
            />

            <DeviceStatusAlert isExpired={isExpired} isRevoked={isRevoked} />

            <DeviceInformationGrid device={device} />
          </div>
        </div>

        <DialogFooter>
          <Button className="w-full sm:w-auto" onClick={onClose} type="button" variant="outline">
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface DeviceStatusAlertProps {
  isExpired: boolean;
  isRevoked: boolean;
}

function DeviceStatusAlert({ isExpired, isRevoked }: DeviceStatusAlertProps): JSX.Element {
  const statusMessage = isRevoked
    ? "Ya no es de confianza, aunque su expiración siga vigente. En tu próximo inicio de sesión se pedirá el código de verificación."
    : isExpired
      ? "El periodo de confianza expiró. En tu próximo inicio de sesión desde este dispositivo se pedirá el código de verificación."
      : "Este dispositivo sigue siendo de confianza. No se te pedirá un código de verificación al iniciar sesión desde aquí hasta su fecha de expiración.";

  const statusIcon = isRevoked || isExpired ? <CircleAlert /> : <CircleCheck />;
  const statusClassName = isRevoked || isExpired ? alertVariants.warning : alertVariants.success;

  return (
    <Alert className={cn(statusClassName, "text-left")}>
      {statusIcon}
      <AlertDescription className="text-left text-inherit">{statusMessage}</AlertDescription>
    </Alert>
  );
}

type DeviceInformationGridProps = Pick<TrustedDeviceDetailsDialogProps, "device">;

function DeviceInformationGrid({ device }: DeviceInformationGridProps): JSX.Element {
  return (
    <div className="overflow-hidden rounded-lg border shadow-xs dark:bg-muted/20">
      <div className="grid grid-cols-1 divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        <DeviceInformationItem
          label="Dirección IP"
          value={valueOrFallback(device.ip, "No disponible")}
          valueClassName="font-mono"
        />

        <DeviceInformationItem label="Registrado" value={formatLongDate(device.createdAt)} />
      </div>
    </div>
  );
}

interface DeviceInformationItemProps {
  label: string;
  value: string;
  valueClassName?: string;
}

function DeviceInformationItem({
  label,
  value,
  valueClassName,
}: DeviceInformationItemProps): JSX.Element {
  return (
    <dl className="min-w-0 space-y-1 px-4 py-3 sm:px-5">
      <dt className="font-mono text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {label}
      </dt>

      <dd className="m-0 text-base font-semibold">
        <span className={cn("wrap-break-word", valueClassName)}>{value}</span>
      </dd>
    </dl>
  );
}

export default TrustedDeviceDetailsDialog;
