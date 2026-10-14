import { type JSX, type SubmitEvent, useState } from "react";

import { useForm } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { KeyRound, RotateCw } from "lucide-react";

import TrustedDeviceOtpField from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceOtpField";
import type {
  TrustedDevice,
  TwoFactorRequiredErrors,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

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
import { Input } from "@/shared/components/shadcn/ui/input";
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

const RECOVERY_CODE_MAX_LENGTH = 21;

function TrustedDeviceReactivationDialog({
  device,
  open,
  onClose,
}: TrustedDeviceReactivationDialogProps): JSX.Element {
  const { data, setData, submit, processing, reset, errors, clearErrors } =
    useForm<ReactivationFormData>({
      otp_code: "",
    });
  const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
  const formErrors: ReactivationFormErrors = errors;

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    setShowRecoveryInput(false);
    onClose();
  };

  const handleToggleRecoveryInput = (): void => {
    setShowRecoveryInput(!showRecoveryInput);
    setData("otp_code", "");
    clearErrors();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    submit(reactivate({ trustedDevice: device.id }), {
      onSuccess: () => {
        onClose();
        reset();
        setShowRecoveryInput(false);
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
          {showRecoveryInput ? (
            <RecoveryCodeField
              error={formErrors.otp_code}
              processing={processing}
              value={data.otp_code}
              onChange={(value) => {
                setData("otp_code", value);
              }}
            />
          ) : (
            <TrustedDeviceOtpField
              id="reactivate-otp"
              label="Código de verificación"
              value={data.otp_code}
              error={formErrors.otp_code}
              disabled={processing}
              onChange={(value) => {
                setData("otp_code", value);
              }}
            />
          )}

          {formErrors.two_factor && (
            <InputError
              aria-live="polite"
              id="reactivate-two-factor-error"
              message={formErrors.two_factor}
            />
          )}

          <Button
            type="button"
            variant="link"
            className="self-center"
            disabled={processing}
            onClick={handleToggleRecoveryInput}
          >
            {showRecoveryInput
              ? "Usar un código de mi aplicación autenticadora"
              : "Usar un código de recuperación"}
          </Button>
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

interface RecoveryCodeFieldProps {
  error: string | undefined;
  processing: boolean;
  value: string;
  onChange: (value: string) => void;
}

function RecoveryCodeField({
  error,
  processing,
  value,
  onChange,
}: RecoveryCodeFieldProps): JSX.Element {
  return (
    <div className="flex flex-col gap-2">
      <LabelForm error={error} htmlFor="reactivate-recovery-code">
        Código de recuperación
      </LabelForm>

      <Input
        id="reactivate-recovery-code"
        name="otp_code"
        type="text"
        required
        autoComplete="off"
        spellCheck={false}
        placeholder="xxxxxxxxxx-xxxxxxxxxx"
        maxLength={RECOVERY_CODE_MAX_LENGTH}
        disabled={processing}
        value={value}
        aria-describedby={error ? "reactivate-recovery-code-error" : undefined}
        aria-invalid={error ? "true" : "false"}
        onChange={(event) => {
          onChange(event.target.value.trim());
        }}
      />

      <p className="text-xs text-muted-foreground">
        Cada código de recuperación solo se puede usar una vez.
      </p>

      {error && (
        <InputError aria-live="polite" id="reactivate-recovery-code-error" message={error} />
      )}
    </div>
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
