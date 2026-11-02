import { type JSX, type SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { Link, useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { CircleAlert, ShieldAlert, ShieldPlus } from "lucide-react";

import type {
  TwoFactorRequiredErrors,
  TwoFactorStatus,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type { TrustedDevicePreview } from "@/modules/setting/modules/trustedDevice/types/trustedDevicePreview";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
import TwoFactorOtpField from "@/modules/setting/shared/components/TwoFactorOtpField";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { store } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { index as twoFactorAuthenticationIndex } from "@/shared/wayfinder/routes/setting/security/two-factor-authentication";

import { InputError, LabelForm } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
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

import { useDateTime, useTranslation } from "@/shared/hooks";

import { valueOrNow } from "@/shared/lib/dateTime";
import { alertVariants, badgeVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface TrustedDeviceAddDialogProps {
  preview: TrustedDevicePreview | null;
  open: boolean;
  onClose: () => void;
}

interface AddDeviceFormData {
  name: string;
  otp_code: string;
}

interface AddDeviceFormErrors extends FormDataErrors<AddDeviceFormData>, TwoFactorRequiredErrors {}

function readTwoFactorStatus(pageProps: Record<string, unknown>): TwoFactorStatus {
  const twoFactorStatus = pageProps["twoFactorStatus"];

  return twoFactorStatus === "confirmed" || twoFactorStatus === "pending"
    ? twoFactorStatus
    : "disabled";
}

function TrustedDeviceAddDialog({
  preview,
  open,
  onClose,
}: TrustedDeviceAddDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { data, setData, submit, processing, reset, errors } = useForm<AddDeviceFormData>({
    name: "",
    otp_code: "",
  });

  const pageProps = usePage().props as Record<string, unknown>;
  const twoFactorStatus = readTwoFactorStatus(pageProps);
  const formErrors: AddDeviceFormErrors = errors;

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    onClose();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>): void => {
    event.preventDefault();

    submit(store(), {
      only: pickReloadKeys(pageProps, [
        "trustedDevices",
        "trustedDevicesCount",
        "stats",
        "currentDeviceMatch",
        "currentDevicePreview",
        "trustedDevicesForRevoke",
        "recentActivity",
      ]),
      onSuccess: () => {
        reset();
        onClose();
      },
      onError: () => {
        setData("otp_code", "");
      },
      preserveScroll: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:min-w-140">
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <ShieldPlus aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.add.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.add.description")}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <DevicePreviewInfo preview={preview} />

          {twoFactorStatus === "confirmed" ? (
            <AddDeviceForm
              handleSubmit={handleSubmit}
              errors={formErrors}
              otpCode={data.otp_code}
              processing={processing}
              setData={setData}
            />
          ) : (
            <TwoFactorRequiredNotice twoFactorStatus={twoFactorStatus} />
          )}

          <Alert className={alertVariants.warning}>
            <CircleAlert aria-hidden="true" />
            <AlertDescription className="text-sm font-normal">
              {t("setting/trusted-device-dialogs.add.personal_only")}
            </AlertDescription>
          </Alert>
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          {twoFactorStatus === "confirmed" ? (
            <Button type="submit" form="add-trusted-device-form" disabled={processing}>
              {processing ? (
                <>
                  <Spinner />
                  {t("setting/trusted-device-dialogs.add.submitting")}
                </>
              ) : (
                t("setting/trusted-device-dialogs.add.submit")
              )}
            </Button>
          ) : (
            <Button asChild>
              <Link href={twoFactorAuthenticationIndex.url()}>
                {t("setting/trusted-device-dialogs.add.go_to_two_factor")}
              </Link>
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DevicePreviewInfoProps = Pick<TrustedDeviceAddDialogProps, "preview">;

interface DevicePreviewMetadataField {
  label: string;
  value: string;
  className: string;
}

function DevicePreviewInfo({ preview }: DevicePreviewInfoProps): JSX.Element {
  const { t } = useTranslation();
  const { formatShortDate, fromNow } = useDateTime();
  const unknownLabel = t("setting/trusted-devices.values.unknown");
  const browser = valueOrFallback(
    [preview?.browser, preview?.browserVersion].filter((value) => value !== "").join(" "),
    unknownLabel,
  );

  const osName = valueOrFallback(preview?.osName, unknownLabel);
  const lastUsedAt = valueOrNow(preview?.lastUsedAt);
  const expiresAt = valueOrNow(preview?.expiresAt);
  const knownDeviceParts = [browser, osName].filter((value) => value !== unknownLabel);
  const deviceDescription =
    knownDeviceParts.length === 2
      ? t("setting/trusted-device-dialogs.add.device_description", { browser, os: osName })
      : (knownDeviceParts[0] ?? unknownLabel);
  const previewDeviceSource = preview ?? { osName: null, isMobile: false };

  const metadataFields: DevicePreviewMetadataField[] = [
    {
      label: t("setting/trusted-devices.info_card.browser"),
      value: browser,
      className: "border-r border-b border-border sm:border-b-0",
    },
    {
      label: t("setting/trusted-devices.info_card.system"),
      value: osName,
      className: "border-b border-border sm:border-r sm:border-b-0",
    },
    {
      label: t("setting/trusted-devices.info_card.last_access"),
      value: fromNow(lastUsedAt),
      className: "border-r border-border",
    },
    {
      label: t("setting/trusted-devices.columns.expires"),
      value: formatShortDate(expiresAt),
      className: "",
    },
  ];

  return (
    <Card className="gap-0 overflow-hidden p-0 shadow-xs">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 bg-muted/30 py-4 dark:bg-muted/20">
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <div className="flex size-12 shrink-0 items-center justify-center rounded-md border bg-background">
            {getDeviceIcon(previewDeviceSource, "size-6 text-foreground")}
          </div>

          <div className="min-w-0">
            <CardTitle className="text-base">
              {t("setting/trusted-device-dialogs.add.detected_device")}
            </CardTitle>

            <CardDescription className="mt-1 truncate">{deviceDescription}</CardDescription>
          </div>
        </div>

        <Badge
          className={cn(badgeVariants.amber, "gap-1.5 border-amber-200 dark:border-amber-500")}
        >
          <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
          {t("setting/trusted-device-dialogs.add.unregistered")}
        </Badge>
      </CardHeader>
      <CardContent className="border-t p-0">
        <dl className="grid grid-cols-2 sm:grid-cols-4">
          {metadataFields.map((metadataField) => (
            <DevicePreviewMetadataItem
              key={metadataField.label}
              className={metadataField.className}
              fieldLabel={metadataField.label}
              fieldValue={metadataField.value}
            />
          ))}
        </dl>
      </CardContent>
    </Card>
  );
}

interface DevicePreviewMetadataItemProps {
  fieldLabel: string;
  fieldValue: string;
  className?: string;
}

function DevicePreviewMetadataItem({
  fieldLabel,
  fieldValue,
  className,
}: DevicePreviewMetadataItemProps): JSX.Element {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1 px-4 py-4 sm:px-5 sm:py-5", className)}>
      <dt className="text-sm font-semibold uppercase">{fieldLabel}</dt>
      <dd className="text-xs wrap-break-word text-muted-foreground">{fieldValue}</dd>
    </div>
  );
}

interface AddDeviceFormProps {
  handleSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
  errors: AddDeviceFormErrors;
  otpCode: string;
  processing: boolean;
  setData: SetDataAction<AddDeviceFormData>;
}

function AddDeviceForm({
  handleSubmit,
  errors,
  otpCode,
  processing,
  setData,
}: AddDeviceFormProps): JSX.Element {
  const { t } = useTranslation();
  return (
    <form id="add-trusted-device-form" onSubmit={handleSubmit} className="flex flex-col gap-4">
      <div className="flex flex-col gap-2">
        <LabelForm htmlFor="add-device-name" error={errors.name}>
          {t("setting/trusted-device-dialogs.add.name_label")}
        </LabelForm>

        <Input
          id="add-device-name"
          name="name"
          placeholder={t("setting/trusted-device-dialogs.add.name_placeholder")}
          aria-describedby={errors.name ? "add-device-name-error" : undefined}
          onChange={(event) => {
            setData("name", event.target.value);
          }}
          aria-invalid={errors.name !== undefined ? "true" : "false"}
        />

        <InputError aria-live="polite" id="add-device-name-error" message={errors.name} />
      </div>

      <TwoFactorOtpField
        id="add-device-otp"
        label={t("setting/common.two_factor_code.otp_label")}
        value={otpCode}
        error={errors.otp_code}
        disabled={processing}
        onChange={(value) => {
          setData("otp_code", value);
        }}
      />

      {errors.two_factor && (
        <InputError
          aria-live="polite"
          id="add-device-two-factor-error"
          message={errors.two_factor}
        />
      )}
    </form>
  );
}

interface TwoFactorRequiredNoticeProps {
  twoFactorStatus: Exclude<TwoFactorStatus, "confirmed">;
}

function TwoFactorRequiredNotice({ twoFactorStatus }: TwoFactorRequiredNoticeProps): JSX.Element {
  const { t } = useTranslation();
  const isPending = twoFactorStatus === "pending";

  return (
    <Alert className={alertVariants.info}>
      <ShieldAlert aria-hidden="true" />
      <AlertTitle>
        {isPending
          ? t("setting/trusted-device-dialogs.add.two_factor_pending_title")
          : t("setting/trusted-device-dialogs.add.two_factor_disabled_title")}
      </AlertTitle>
      <AlertDescription>
        {isPending
          ? t("setting/trusted-device-dialogs.add.two_factor_pending_description")
          : t("setting/trusted-device-dialogs.add.two_factor_disabled_description")}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceAddDialog;
