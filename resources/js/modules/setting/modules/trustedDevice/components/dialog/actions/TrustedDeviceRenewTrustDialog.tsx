import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import dayjs from "dayjs";
import { ArrowBigDown, CalendarClock, CalendarRange, CircleAlert, RefreshCcw } from "lucide-react";

import TrustedDeviceSummaryCard from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type {
  TrustedDevice,
  TwoFactorRequiredErrors,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import TwoFactorOtpField from "@/modules/setting/shared/components/TwoFactorOtpField";

import { renew as renewTrustedDevice } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError } from "@/shared/components/form";
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

import { useDateTime, useTranslation } from "@/shared/hooks";

import { alertVariants, badgeVariants } from "@/shared/lib/styling";

interface TrustedDeviceRenewTrustDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface RenewFormData {
  otp_code: string;
}

interface RenewFormErrors extends FormDataErrors<RenewFormData>, TwoFactorRequiredErrors {}

const TRUST_RENEWAL_DAYS = 30;

function TrustedDeviceRenewTrustDialog({
  device,
  open,
  onClose,
}: TrustedDeviceRenewTrustDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { fromNow } = useDateTime();
  const { data, setData, submit, processing, reset, errors } = useForm<RenewFormData>({
    otp_code: "",
  });
  const formErrors: RenewFormErrors = errors;
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
      only: pickReloadKeys(pageProps, ["trustedDevices", "recentActivity"]),
      onSuccess: () => {
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
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <RefreshCcw aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.renew.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.renew.description")}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <TrustedDeviceSummaryCard device={device} lastUsedAt={fromNow(device.lastUsedAt)} />

          <RenewDeviceForm
            device={device}
            errors={formErrors}
            handleSubmit={handleSubmit}
            otpCode={data.otp_code}
            processing={processing}
            setData={setData}
          />

          <RenewInfoAlert />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button type="submit" form="renew-trusted-device-form" disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                {t("setting/trusted-device-dialogs.renew.submitting")}
              </>
            ) : (
              t("setting/trusted-device-dialogs.renew.submit")
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type RenewDeviceFormProps = Pick<TrustedDeviceRenewTrustDialogProps, "device"> & {
  errors: RenewFormErrors;
  handleSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
  otpCode: string;
  processing: boolean;
  setData: SetDataAction<RenewFormData>;
};

function RenewDeviceForm({
  device,
  errors,
  handleSubmit,
  otpCode,
  processing,
  setData,
}: RenewDeviceFormProps): JSX.Element {
  const { t } = useTranslation();
  const { formatLongDate } = useDateTime();
  const newExpiresAt = dayjs().add(TRUST_RENEWAL_DAYS, "day");
  const newExpiresAtFormatted = formatLongDate(newExpiresAt.toISOString());

  return (
    <form id="renew-trusted-device-form" onSubmit={handleSubmit} className="flex flex-col gap-2">
      <Item variant="outline" className="dark:bg-muted/20">
        <ItemMedia>
          <CalendarClock />
        </ItemMedia>
        <ItemContent>
          <ItemTitle className="text-xs font-medium">
            {t("setting/trusted-device-dialogs.renew.current_expiration")}
          </ItemTitle>
          <ItemDescription className="text-sm font-normal">
            {formatLongDate(device.expiresAt)}
          </ItemDescription>
        </ItemContent>
      </Item>

      <div
        aria-hidden
        className="flex size-9 items-center justify-center self-center rounded-md border bg-background dark:bg-input/30 [&_svg]:size-4"
      >
        <ArrowBigDown />
      </div>

      <Item variant="outline" className="dark:bg-muted/20">
        <ItemMedia>
          <CalendarRange />
        </ItemMedia>
        <ItemContent>
          <ItemTitle className="text-xs font-medium">
            {t("setting/trusted-device-dialogs.renew.new_expiration")}
          </ItemTitle>
          <ItemDescription className="text-sm font-normal">{newExpiresAtFormatted}</ItemDescription>
        </ItemContent>
        <ItemActions>
          <Badge className={badgeVariants.success}>
            + {t("setting/trusted-device-dialogs.renew.renewal_period")}
          </Badge>
        </ItemActions>
      </Item>

      <TwoFactorOtpField
        id="renew-device-otp"
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
          id="renew-device-two-factor-error"
          message={errors.two_factor}
        />
      )}
    </form>
  );
}

function RenewInfoAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert className={alertVariants.success}>
      <CircleAlert />

      <AlertTitle className="line-clamp-3">
        {t("setting/trusted-device-dialogs.renew.notice")}
      </AlertTitle>
    </Alert>
  );
}

export default TrustedDeviceRenewTrustDialog;
