import type { JSX, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";
import { MdOutlineLaptopMac } from "react-icons/md";

import dayjs from "dayjs";
import { ArrowBigDown, CalendarClock, CalendarRange, CircleAlert, RefreshCcw } from "lucide-react";

import { renew as renewTrustedDevice } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { Alert, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import {
  Item,
  ItemActions,
  ItemContent,
  ItemDescription,
  ItemMedia,
  ItemTitle,
} from "@/shared/components/shadcn/ui/item";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import type { TrustedDevice } from "../../../types/trustedDevice";

interface RenewTrustDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

const TRUST_RENEWAL_DAYS = 30;
const TRUST_RENEWAL_LABEL = "1 mes";

const deviceLabel = (device: TrustedDevice): string => {
  return device.name ?? device.userAgent ?? "Dispositivo desconocido";
};

const fromNow = (iso: string | null): string => {
  if (iso === null) {
    return "nunca";
  }

  return dayjs(iso).fromNow();
};

function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }
  return dayjs(iso).format("D [de] MMMM [del] YYYY, h:mm A");
}

function RenewTrustDialog({ device, open, onClose }: RenewTrustDialogProps): JSX.Element {
  const { submit, processing, reset } = useForm();

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    onClose();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    submit(renewTrustedDevice({ trustedDevice: device.id }), {
      only: ["trustedDevices"],
      onSuccess: () => {
        onClose();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <RefreshCcw className="h-5 w-5 text-muted-foreground" />
              Renovar confianza
            </div>
          </DialogTitle>
          <DialogDescription>
            Extiende el periodo de confianza para evitar que se te solicite el codigo de
            verificacion en este dispositivo.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <DeviceInfoCard device={device} />

          <RenewDeviceForm device={device} handleSubmit={handleSubmit} />

          <RenewInfoAlert />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form="renew-trusted-device-form" disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                Renovando...
              </>
            ) : (
              "Renovar confianza"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DeviceInfoCardProps = Pick<RenewTrustDialogProps, "device">;

function DeviceInfoCard({ device }: DeviceInfoCardProps): JSX.Element {
  return (
    <div className="flex min-w-0 items-start gap-2.5 rounded-xl border p-4 shadow-xs dark:dark:bg-input/10">
      <div className="flex h-14 w-14 shrink-0 rounded-md border border-violet-100 bg-violet-100/50 p-2 dark:border-violet-200/10 dark:bg-violet-900/20">
        <MdOutlineLaptopMac className="h-10 w-10 text-violet-700 dark:text-violet-500" />
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
        <span className="block truncate font-medium" title={deviceLabel(device)}>
          {deviceLabel(device)}
        </span>

        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          {device.browser !== null && device.browser !== "" && (
            <span className="truncate text-sm text-muted-foreground">
              {device.osName} - {device.browser}
            </span>
          )}

          <FaCircle className="h-1 w-1 shrink-0" />

          <span className="truncate text-sm text-muted-foreground">
            Último uso: {fromNow(device.lastUsedAt)}
          </span>
        </div>
      </div>
    </div>
  );
}

type RenewDeviceFormProps = Pick<RenewTrustDialogProps, "device"> & {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
};

function RenewDeviceForm({ device, handleSubmit }: RenewDeviceFormProps): JSX.Element {
  const newExpiresAt = dayjs().add(TRUST_RENEWAL_DAYS, "day");

  return (
    <form id="renew-trusted-device-form" onSubmit={handleSubmit} className="flex flex-col gap-2">
      <Item variant="outline">
        <ItemMedia>
          <CalendarClock />
        </ItemMedia>
        <ItemContent>
          <ItemTitle>Expiracion actual</ItemTitle>
          <ItemDescription>{formatLongDate(device.expiresAt)}</ItemDescription>
        </ItemContent>
      </Item>

      <div
        aria-hidden
        className="flex size-9 items-center justify-center self-center rounded-md border bg-background dark:bg-input/30 [&_svg]:size-4"
      >
        <ArrowBigDown />
      </div>

      <Item variant="outline">
        <ItemMedia>
          <CalendarRange />
        </ItemMedia>
        <ItemContent>
          <ItemTitle>Nueva expiración</ItemTitle>
          <ItemDescription>{newExpiresAt.format("D [de] MMMM [del] YYYY, h:mm A")}</ItemDescription>
        </ItemContent>
        <ItemActions>
          <Badge className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
            + {TRUST_RENEWAL_LABEL}
          </Badge>
        </ItemActions>
      </Item>
    </form>
  );
}

function RenewInfoAlert(): JSX.Element {
  return (
    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-500">
      <CircleAlert />

      <AlertTitle className="line-clamp-3">
        No se te pedira el codigo de verificacion en este dispositivo hasta la nueva fecha de
        expiracion.
      </AlertTitle>
    </Alert>
  );
}

export default RenewTrustDialog;
