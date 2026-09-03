import type { JSX, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { REGEXP_ONLY_DIGITS } from "input-otp";
import { KeyRound, RotateCw } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { OTP_MAX_LENGTH } from "@/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth";

import { reactivate } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm } from "@/shared/components/form";
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
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from "@/shared/components/shadcn/ui/input-otp";
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

function TrustedDeviceReactivationDialog({
  device,
  open,
  onClose,
}: TrustedDeviceReactivationDialogProps): JSX.Element {
  const { data, setData, submit, processing, reset, errors } = useForm<ReactivationFormData>({
    otp_code: "",
  });

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
      preserveScroll: true,
      preserveState: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <RotateCw className="h-5 w-5 text-muted-foreground" />
              Reactivar dispositivo
            </div>
          </DialogTitle>
          <DialogDescription>
            Para volver a confiar en este dispositivo, ingresa el código de verificación de tu
            aplicación autenticadora.
          </DialogDescription>
        </DialogHeader>

        <ReactivationInstructions deviceName={device.name ?? "este dispositivo"} />

        <form id="reactivation-form" onSubmit={handleSubmit} className="grid gap-4">
          <div className="flex flex-col items-center justify-center gap-2 text-center">
            <LabelForm error={errors.otp_code} htmlFor="reactivate-otp">
              Código de verificación
            </LabelForm>

            <div className="flex w-full items-center justify-center">
              <InputOTP
                id="reactivate-otp"
                name="otp_code"
                required
                autoFocus
                onChange={(value) => {
                  setData("otp_code", value);
                }}
                disabled={processing}
                maxLength={OTP_MAX_LENGTH}
                pattern={REGEXP_ONLY_DIGITS}
                value={data.otp_code}
              >
                <InputOTPGroup className="*:data-[slot=input-otp-slot]:h-12 *:data-[slot=input-otp-slot]:w-12 *:data-[slot=input-otp-slot]:text-lg">
                  {Array.from({ length: OTP_MAX_LENGTH / 2 }, (_, index) => (
                    <InputOTPSlot index={index} key={index} />
                  ))}
                </InputOTPGroup>

                <InputOTPSeparator className="mx-2" />

                <InputOTPGroup className="*:data-[slot=input-otp-slot]:h-12 *:data-[slot=input-otp-slot]:w-12 *:data-[slot=input-otp-slot]:text-lg">
                  {Array.from({ length: OTP_MAX_LENGTH / 2 }, (_, index) => (
                    <InputOTPSlot index={index + OTP_MAX_LENGTH / 2} key={index} />
                  ))}
                </InputOTPGroup>
              </InputOTP>
            </div>

            {errors.otp_code && <InputError message={errors.otp_code} />}
          </div>
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
        Ingresa el código de 6 dígitos de tu aplicación autenticadora.
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceReactivationDialog;
