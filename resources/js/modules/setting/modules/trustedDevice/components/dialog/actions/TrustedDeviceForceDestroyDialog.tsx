import type { JSX, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangleIcon, Trash2 } from "lucide-react";

import TrustedDeviceSummaryCard from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

import { forceDestroy } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
import { Alert, AlertDescription } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Checkbox } from "@/shared/components/shadcn/ui/checkbox";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Label } from "@/shared/components/shadcn/ui/label";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { alertVariants, buttonVariants } from "@/shared/lib/styling";

interface TrustedDeviceForceDestroyDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface ForceDestroyFormData {
  password: string;
  terms: boolean;
}

function TrustedDeviceForceDestroyDialog({
  device,
  open,
  onClose,
}: TrustedDeviceForceDestroyDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { formatLongDate, fromNow } = useDateTime();
  const { data, setData, submit, processing, reset, errors } = useForm<ForceDestroyFormData>({
    password: "",
    terms: false,
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

    submit(forceDestroy({ trustedDevice: device.id }), {
      onSuccess: () => {
        onClose();
        reset();
      },
      preserveScroll: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="[&_dt]:font-normal">
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Trash2 aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.force_destroy.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.force_destroy.description", {
              device: device.name ?? t("setting/trusted-device-dialogs.this_device"),
            })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <TrustedDeviceSummaryCard
            device={device}
            lastUsedAt={fromNow(device.lastUsedAt)}
            expiration={device.deletedAt !== null ? formatLongDate(device.deletedAt) : "—"}
          />

          <ForceDestroyConsequencesAlert />

          <ForceDestroyForm
            data={data}
            errors={errors}
            handleSubmit={handleSubmit}
            setData={setData}
          />
        </div>

        <DialogFooter className="mt-1.5">
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button
            type="submit"
            form="force-destroy-trusted-device-form"
            disabled={processing}
            className={buttonVariants.destructive}
          >
            {processing ? (
              <>
                <Spinner />
                {t("setting/trusted-device-dialogs.force_destroy.submitting")}
              </>
            ) : (
              t("setting/trusted-device-dialogs.force_destroy.submit")
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ForceDestroyConsequencesAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert aria-labelledby="force-destroy-warning-title" className={alertVariants.destructive}>
      <AlertTriangleIcon aria-hidden="true" />
      <h3
        className="col-start-2 min-h-4 text-sm font-medium tracking-tight"
        id="force-destroy-warning-title"
      >
        {t("setting/trusted-device-dialogs.force_destroy.consequences_title")}
      </h3>
      <AlertDescription className="text-sm font-normal">
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>{t("setting/trusted-device-dialogs.force_destroy.consequences.deleted")}</li>
          <li>{t("setting/trusted-device-dialogs.force_destroy.consequences.no_reactivation")}</li>
          <li>{t("setting/trusted-device-dialogs.force_destroy.consequences.history")}</li>
        </ul>
      </AlertDescription>
    </Alert>
  );
}

interface ForceDestroyFormProps {
  data: ForceDestroyFormData;
  errors: Partial<Record<keyof ForceDestroyFormData, string>>;
  handleSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
  setData: <K extends keyof ForceDestroyFormData>(key: K, value: ForceDestroyFormData[K]) => void;
}

function ForceDestroyForm({
  handleSubmit,
  errors,
  setData,
  data,
}: ForceDestroyFormProps): JSX.Element {
  const { t } = useTranslation();
  return (
    <form id="force-destroy-trusted-device-form" onSubmit={handleSubmit} className="grid gap-4">
      <div className="flex flex-col gap-2">
        <Label htmlFor="force-destroy-trusted-device">
          {t("setting/trusted-device-dialogs.password_confirmation.prompt")}
        </Label>

        <PasswordInput
          id="force-destroy-trusted-device"
          name="password"
          required
          autoFocus
          placeholder={t("setting/trusted-device-dialogs.password_confirmation.placeholder")}
          aria-describedby={errors.password ? "force-destroy-password-error" : undefined}
          onChange={(event) => {
            setData("password", event.target.value);
          }}
          aria-invalid={errors.password ? "true" : "false"}
        />

        {errors.password && (
          <InputError
            aria-live="polite"
            id="force-destroy-password-error"
            message={errors.password}
          />
        )}
      </div>

      <div className="flex gap-2">
        <Checkbox
          aria-describedby={errors.terms ? "force-destroy-terms-error" : undefined}
          id="force-destroy-trusted-device-terms"
          name="terms"
          checked={data.terms}
          onCheckedChange={(checked) => {
            setData("terms", checked === true);
          }}
          aria-invalid={errors.terms ? "true" : "false"}
        />

        <LabelForm htmlFor="force-destroy-trusted-device-terms">
          {t("setting/trusted-device-dialogs.force_destroy.terms")}
        </LabelForm>
      </div>

      {errors.terms && (
        <InputError aria-live="polite" id="force-destroy-terms-error" message={errors.terms} />
      )}
    </form>
  );
}

export default TrustedDeviceForceDestroyDialog;
