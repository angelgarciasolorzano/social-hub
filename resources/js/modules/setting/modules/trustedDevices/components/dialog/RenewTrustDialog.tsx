import type { JSX, SubmitEvent } from "react";

import { useForm, usePage } from "@inertiajs/react";

import dayjs from "dayjs";
import { ArrowBigDown, CalendarClock, CalendarRange, CircleAlert, RefreshCcw } from "lucide-react";

import DeviceSummaryCard from "@/modules/setting/modules/trustedDevices/components/ui/DeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevices/utils/inertiaPageProps";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

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

import { alertVariants, badgeVariants } from "@/shared/lib/styling";

interface RenewTrustDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

const TRUST_RENEWAL_DAYS = 30;
const TRUST_RENEWAL_LABEL = "1 mes";

function RenewTrustDialog({ device, open, onClose }: RenewTrustDialogProps): JSX.Element {
  const { submit, processing, reset } = useForm();
  const pageProps = usePage().props as Record<string, unknown>;

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
      only: pickReloadKeys(pageProps, ["trustedDevices"]),
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
          <DeviceSummaryCard device={device} lastUsedAt={fromNow(device.lastUsedAt)} />

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

type RenewDeviceFormProps = Pick<RenewTrustDialogProps, "device"> & {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
};

function RenewDeviceForm({ device, handleSubmit }: RenewDeviceFormProps): JSX.Element {
  const newExpiresAt = dayjs().add(TRUST_RENEWAL_DAYS, "day");
  const newExpiresAtFormatted = formatLongDate(newExpiresAt.toISOString());

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
          <ItemDescription>{newExpiresAtFormatted}</ItemDescription>
        </ItemContent>
        <ItemActions>
          <Badge className={badgeVariants.success}>+ {TRUST_RENEWAL_LABEL}</Badge>
        </ItemActions>
      </Item>
    </form>
  );
}

function RenewInfoAlert(): JSX.Element {
  return (
    <Alert className={alertVariants.success}>
      <CircleAlert />

      <AlertTitle className="line-clamp-3">
        No se te pedira el codigo de verificacion en este dispositivo hasta la nueva fecha de
        expiracion.
      </AlertTitle>
    </Alert>
  );
}

export default RenewTrustDialog;
