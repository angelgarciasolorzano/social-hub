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

import { useTranslation } from "@/shared/hooks";

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
  const { t } = useTranslation();
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
              {t("setting/trusted-device-dialogs.reactivation.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.reactivation.description")}
          </DialogDescription>
        </DialogHeader>

        <ReactivationInstructions
          deviceName={device.name ?? t("setting/trusted-device-dialogs.this_device")}
        />

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
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button type="submit" form="reactivation-form" disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                {t("setting/trusted-device-dialogs.reactivation.submitting")}
              </>
            ) : (
              t("setting/trusted-device-dialogs.reactivation.submit")
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
  const { t } = useTranslation();
  return (
    <Alert className={alertVariants.info}>
      <KeyRound />
      <AlertTitle>{t("setting/trusted-device-dialogs.reactivation.why_code_title")}</AlertTitle>
      <AlertDescription>
        {t("setting/trusted-device-dialogs.reactivation.why_code_before")}{" "}
        <strong>{deviceName}</strong>{" "}
        {t("setting/trusted-device-dialogs.reactivation.why_code_after")}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceReactivationDialog;
