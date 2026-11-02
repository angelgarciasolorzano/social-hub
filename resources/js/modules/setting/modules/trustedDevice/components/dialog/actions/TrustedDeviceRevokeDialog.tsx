import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { AlertTriangleIcon, Trash2 } from "lucide-react";

import TrustedDeviceSummaryCard from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";

import { destroy } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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

import { cn } from "@/shared/lib";
import { alertVariants } from "@/shared/lib/styling";

interface TrustedDeviceRevokeDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface RevokeDeviceFormData {
  password: string;
  terms: boolean;
}

function TrustedDeviceRevokeDialog({
  device,
  open,
  onClose,
}: TrustedDeviceRevokeDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { formatLongDate, fromNow } = useDateTime();
  const { data, setData, submit, processing, reset, errors } = useForm<RevokeDeviceFormData>({
    password: "",
    terms: false,
  });

  const pageProps = usePage().props as Record<string, unknown>;

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    onClose();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>) => {
    event.preventDefault();

    submit(destroy({ trustedDevice: device.id }), {
      only: pickReloadKeys(pageProps, [
        "trustedDevices",
        "stats",
        "currentDeviceMatch",
        "currentDevicePreview",
        "trustedDevicesForRevoke",
        "recentActivity",
      ]),
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
              {t("setting/trusted-device-dialogs.revoke.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.revoke.description", {
              device: device.name ?? t("setting/trusted-device-dialogs.this_device"),
            })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <TrustedDeviceSummaryCard
            device={device}
            expiration={formatLongDate(device.expiresAt)}
            lastUsedAt={fromNow(device.lastUsedAt)}
          />

          <RevokeConsequencesAlert />

          <RevokeDeviceForm
            data={data}
            errors={errors}
            handleSubmit={handleSubmit}
            setData={setData}
          />
        </div>

        <DialogFooter className="mt-1.5">
          <DialogClose asChild>
            <Button disabled={processing} type="button" variant="outline">
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button disabled={processing} form="revoke-trusted-device-form" type="submit">
            {processing ? (
              <>
                <Spinner />
                {t("setting/trusted-device-dialogs.revoke.submitting")}
              </>
            ) : (
              t("setting/trusted-device-dialogs.revoke.submit")
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function RevokeConsequencesAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert
      aria-labelledby="revoke-device-warning-title"
      className={cn(alertVariants.destructive, "my-2")}
    >
      <AlertTriangleIcon aria-hidden="true" />
      <h3
        className="col-start-2 min-h-4 text-sm font-medium tracking-tight"
        id="revoke-device-warning-title"
      >
        {t("setting/trusted-device-dialogs.revoke.consequences_title")}
      </h3>
      <AlertDescription className="text-sm font-normal">
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>{t("setting/trusted-device-dialogs.revoke.consequences.not_trusted")}</li>
          <li>{t("setting/trusted-device-dialogs.revoke.consequences.code_again")}</li>
        </ul>
      </AlertDescription>
    </Alert>
  );
}

interface RevokeDeviceFormProps {
  data: RevokeDeviceFormData;
  errors: FormDataErrors<RevokeDeviceFormData>;
  handleSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
  setData: SetDataAction<RevokeDeviceFormData>;
}

function RevokeDeviceForm(props: RevokeDeviceFormProps): JSX.Element {
  const { t } = useTranslation();
  const { handleSubmit, errors, setData, data } = props;

  return (
    <form id="revoke-trusted-device-form" className="grid gap-4" onSubmit={handleSubmit}>
      <div className="flex flex-col gap-2">
        <Label htmlFor="revoke-trusted-device">
          {t("setting/trusted-device-dialogs.password_confirmation.prompt")}
        </Label>

        <PasswordInput
          aria-describedby={errors.password ? "revoke-device-password-error" : undefined}
          aria-invalid={errors.password ? "true" : "false"}
          autoFocus
          id="revoke-trusted-device"
          name="password"
          placeholder={t("setting/trusted-device-dialogs.password_confirmation.placeholder")}
          required
          onChange={(event) => {
            setData("password", event.target.value);
          }}
        />

        {errors.password && (
          <InputError
            aria-live="polite"
            id="revoke-device-password-error"
            message={errors.password}
          />
        )}
      </div>

      <div className="flex gap-2">
        <Checkbox
          aria-describedby={errors.terms ? "revoke-device-terms-error" : undefined}
          aria-invalid={errors.terms ? "true" : "false"}
          checked={data.terms}
          id="revoke-trusted-device-terms"
          name="terms"
          onCheckedChange={(checked) => {
            setData("terms", checked === true);
          }}
        />

        <LabelForm htmlFor="revoke-trusted-device-terms">
          {t("setting/trusted-device-dialogs.revoke.terms")}
        </LabelForm>
      </div>

      {errors.terms && (
        <InputError aria-live="polite" id="revoke-device-terms-error" message={errors.terms} />
      )}
    </form>
  );
}

export default TrustedDeviceRevokeDialog;
