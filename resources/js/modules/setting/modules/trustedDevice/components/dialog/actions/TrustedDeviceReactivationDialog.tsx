import type { JSX, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { KeyRound, RotateCw } from "lucide-react";

import type {
  TrustedDevice,
  TwoFactorRequiredErrors,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import TwoFactorCodeField from "@/modules/setting/shared/components/TwoFactorCodeField";

import { reactivate } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceReactivationDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface ReactivationFormData {
  otp_code: string;
}

interface ReactivationFormErrors
  extends FormDataErrors<ReactivationFormData>, TwoFactorRequiredErrors {}

function TrustedDeviceReactivationDialog({
  device,
  open,
  onClose,
}: TrustedDeviceReactivationDialogProps): JSX.Element {
  const { data, setData, submit, processing, reset, errors, clearErrors } =
    useForm<ReactivationFormData>({
      otp_code: "",
    });
  const formErrors: ReactivationFormErrors = errors;

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    onClose();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    submit(reactivate({ trustedDevice: device.id }), {
      onSuccess: () => {
        onClose();
        reset();
      },
      onError: () => {
        setData("otp_code", "");
      },
      preserveScroll: true,
      preserveState: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <RotateCw aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Reactivar dispositivo
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Para volver a confiar en este dispositivo, ingresa un código de verificación de tu
            aplicación autenticadora o uno de tus códigos de recuperación. Recordar un navegador es
            una comodidad temporal, no un segundo factor de autenticación independiente.
          </DialogDescription>
        </DialogHeader>

        <ReactivationInstructions deviceName={device.name ?? "este dispositivo"} />

        <form id="reactivation-form" onSubmit={handleSubmit} className="grid gap-4">
          <TwoFactorCodeField
            id="reactivate-code"
            name="otp_code"
            value={data.otp_code}
            error={formErrors.otp_code}
            disabled={processing}
            onChange={(value) => {
              setData("otp_code", value);
            }}
            onModeChange={clearErrors}
          />

          {formErrors.two_factor && (
            <InputError
              aria-live="polite"
              id="reactivate-two-factor-error"
              message={formErrors.two_factor}
            />
          )}
        </form>

        <DialogFooter className="mt-1.5">
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form="reactivation-form" disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                Reactivando...
              </>
            ) : (
              "Reactivar"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface ReactivationInstructionsProps {
  deviceName: string;
}

function ReactivationInstructions({ deviceName }: ReactivationInstructionsProps): JSX.Element {
  return (
    <Alert className={alertVariants.info}>
      <KeyRound />
      <AlertTitle>¿Por qué pedimos código?</AlertTitle>
      <AlertDescription>
        Por seguridad, reactivar <strong>{deviceName}</strong> requiere autenticarte de nuevo.
        Ingresa el código de 6 dígitos de tu aplicación autenticadora o uno de tus códigos de
        recuperación.
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceReactivationDialog;
