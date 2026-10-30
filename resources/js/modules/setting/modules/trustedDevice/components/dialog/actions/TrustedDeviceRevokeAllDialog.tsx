import type { JSX, SubmitEvent } from "react";
import { useEffect, useRef, useState } from "react";

import { router, type SetDataAction, useForm, usePage } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";

import type { FormDataErrors } from "@inertiajs/core";
import {
  AlertTriangleIcon,
  CircleAlert,
  MonitorOff,
  MonitorSmartphone,
  Trash2,
} from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { destroyAll as destroyAllTrustedDevices } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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
import { ScrollArea } from "@/shared/components/shadcn/ui/scroll-area";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { alertVariants, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import type { SharedData } from "@/shared/types";

interface TrustedDeviceRevokeAllDialogProps {
  open: boolean;
  onClose: () => void;
}

interface TrustedDeviceRevokeAllDialogPageProps extends SharedData {
  trustedDevicesForRevoke?: TrustedDevice[];
}

interface RevokeDeviceFormData {
  password: string;
  terms: boolean;
}

function TrustedDeviceRevokeAllDialog({
  open,
  onClose,
}: TrustedDeviceRevokeAllDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { data, setData, submit, processing, reset, errors } = useForm<RevokeDeviceFormData>({
    password: "",
    terms: false,
  });

  const page = usePage<TrustedDeviceRevokeAllDialogPageProps>();
  const { trustedDevicesForRevoke } = page.props;
  const pageProps = page.props as Record<string, unknown>;

  const hasRequestedDevicesRef = useRef(false);
  const [hasFreshDevices, setHasFreshDevices] = useState(false);

  useEffect(() => {
    if (hasRequestedDevicesRef.current) return;

    hasRequestedDevicesRef.current = true;

    router.reload({
      only: ["trustedDevicesForRevoke"],
      onFinish: () => {
        setHasFreshDevices(true);
      },
    });
  }, []);

  const handleOpenChange = (nextOpen: boolean): void => {
    if (processing && !nextOpen) {
      return;
    }

    reset();
    onClose();
  };

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>) => {
    event.preventDefault();

    submit(destroyAllTrustedDevices(), {
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

  const isLoading = !hasFreshDevices || trustedDevicesForRevoke === undefined;
  const devices = trustedDevicesForRevoke ?? [];

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-3xl min-w-2xl">
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Trash2 aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-device-dialogs.revoke_all.title")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.revoke_all.description")}
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <TrustedDeviceRevokeAllDialogSkeleton />
        ) : (
          <>
            <RevokeConsequencesAlert />

            <AffectedDevicesList devices={devices} />

            <RevokeDeviceForm
              handleSubmit={handleSubmit}
              errors={errors}
              setData={setData}
              data={data}
              disabled={devices.length === 0}
            />

            <DialogFooter className="flex items-center gap-4 border-t sm:justify-between">
              <Alert className="border-none bg-transparent">
                <CircleAlert className="text-purple-500 dark:text-purple-500" />
                <AlertTitle className="line-clamp-3 font-normal text-muted-foreground">
                  {t("setting/trusted-device-dialogs.revoke_all.single_hint_before")}{" "}
                  <strong className="text-purple-700 dark:text-purple-500">
                    {t("setting/trusted-device-dialogs.revoke_all.single_hint_emphasis")}
                  </strong>{" "}
                  {t("setting/trusted-device-dialogs.revoke_all.single_hint_after")}
                </AlertTitle>
              </Alert>

              <div className="flex items-center justify-center gap-2">
                <DialogClose asChild>
                  <Button type="button" variant="outline" disabled={processing}>
                    {t("setting/common.actions.cancel")}
                  </Button>
                </DialogClose>

                <Button
                  type="submit"
                  form="revoke-trusted-device-form"
                  disabled={processing || devices.length === 0}
                >
                  {processing ? (
                    <>
                      <Spinner />
                      {t("setting/trusted-device-dialogs.revoke_all.submitting")}
                    </>
                  ) : (
                    t("setting/trusted-device-dialogs.revoke_all.submit")
                  )}
                </Button>
              </div>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

function TrustedDeviceRevokeAllDialogSkeleton(): JSX.Element {
  const deviceSkeletonKeys = ["first", "second", "third", "fourth", "fifth"];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-start gap-3 rounded-lg border p-4">
        <Skeleton className="size-5 shrink-0 rounded-full" />

        <div className="flex min-w-0 flex-1 flex-col gap-2">
          <Skeleton className="h-5 w-28 max-w-full" />
          <Skeleton className="h-4 w-full max-w-full" />
          <Skeleton className="h-4 w-4/5 max-w-full" />
        </div>
      </div>

      <div className="flex flex-col gap-4 rounded-xl border p-4 shadow-xs">
        <div className="flex min-w-0 items-start gap-4">
          <Skeleton className="size-14 shrink-0 rounded-full" />

          <div className="flex min-w-0 flex-1 flex-col gap-2">
            <Skeleton className="h-5 w-48 max-w-full" />
            <Skeleton className="h-4 w-full max-w-full" />
          </div>
        </div>

        <div className="flex h-40 flex-col gap-3 rounded-xl border p-3">
          {deviceSkeletonKeys.map((deviceSkeletonKey) => (
            <div className="flex min-w-0 items-center gap-3" key={deviceSkeletonKey}>
              <Skeleton className="size-4 shrink-0 rounded-full" />
              <Skeleton className="h-4 w-28 max-w-full" />
              <Skeleton className="h-4 w-100 max-w-full" />
            </div>
          ))}
        </div>
      </div>

      <div className="flex flex-col gap-4">
        <Skeleton className="h-4 w-64 max-w-full" />
        <Skeleton className="h-10 w-full max-w-full" />

        <div className="flex items-center gap-2">
          <Skeleton className="size-4 shrink-0" />
          <Skeleton className="h-4 w-72 max-w-full" />
        </div>
      </div>

      <div className="flex items-center justify-end gap-2 border-t pt-4">
        <Skeleton className="h-9 w-20 max-w-full" />
        <Skeleton className="h-9 w-32 max-w-full" />
      </div>
    </div>
  );
}

function RevokeConsequencesAlert(): JSX.Element {
  const { t } = useTranslation();
  return (
    <Alert aria-labelledby="revoke-all-warning-title" className={alertVariants.destructive}>
      <AlertTriangleIcon aria-hidden="true" />
      <h3
        className="col-start-2 min-h-4 text-sm font-medium tracking-tight"
        id="revoke-all-warning-title"
      >
        {t("setting/trusted-device-dialogs.revoke_all.consequences_title")}
      </h3>
      <AlertDescription className="text-sm font-normal">
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>{t("setting/trusted-device-dialogs.revoke_all.consequences.all_removed")}</li>
          <li>{t("setting/trusted-device-dialogs.revoke_all.consequences.code_again")}</li>
          <li>{t("setting/trusted-device-dialogs.revoke_all.consequences.irreversible")}</li>
        </ul>
      </AlertDescription>
    </Alert>
  );
}

interface AffectedDevicesListProps {
  devices: TrustedDevice[];
}

function AffectedDevicesList({ devices }: AffectedDevicesListProps): JSX.Element {
  const { t, tChoice } = useTranslation();
  const { formatLongDate } = useDateTime();
  const deviceCount = devices.length;
  const hasDevices = deviceCount > 0;
  const headline = hasDevices
    ? tChoice("setting/trusted-device-dialogs.revoke_all.devices_count", deviceCount)
    : t("setting/trusted-device-dialogs.revoke_all.no_devices_title");
  const description = hasDevices
    ? t("setting/trusted-device-dialogs.revoke_all.includes_all")
    : t("setting/trusted-device-dialogs.revoke_all.no_devices_description");

  return (
    <div className="flex flex-col gap-4 rounded-xl border p-4 shadow-xs">
      <div className="flex min-w-0 items-start gap-4">
        <div
          className={cn(
            iconColorVariants.violet.iconBgClass,
            "flex h-14 w-14 shrink-0 rounded-full border border-violet-100 p-2 dark:border-violet-200/10",
          )}
        >
          <MonitorSmartphone className={cn("h-10 w-10", iconColorVariants.violet.iconFgClass)} />
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
          <h3 className="text-base font-semibold">{headline}</h3>
          <p className="text-sm font-normal text-muted-foreground">{description}</p>
        </div>
      </div>

      {hasDevices ? (
        <ScrollArea className="h-40 rounded-xl border py-3 dark:bg-input/10">
          <ul className="divide-y" role="list">
            {devices.map((device) => (
              <li className="mx-4 flex items-center gap-2 py-1 text-sm" key={device.id}>
                {getDeviceIcon(device, "shrink-0")}

                <span className="max-w-20 truncate font-medium">{device.name}</span>

                <FaCircle aria-hidden="true" className="h-1 w-1 text-muted-foreground" />

                <span className="text-xs font-normal text-muted-foreground">
                  {device.browser}
                  {device.browserVersion !== null && device.browserVersion !== "" && (
                    <> {device.browserVersion}</>
                  )}
                </span>

                <FaCircle aria-hidden="true" className="h-1 w-1 text-muted-foreground" />

                <span className="truncate text-xs font-normal text-muted-foreground">
                  {t("setting/trusted-device-dialogs.revoke_all.device_expires", {
                    date: formatLongDate(device.expiresAt),
                  })}
                </span>
              </li>
            ))}
          </ul>
        </ScrollArea>
      ) : (
        <div className="flex h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-sm text-muted-foreground dark:bg-input/10">
          <EmptyState
            icon={MonitorOff}
            title={t("setting/trusted-device-dialogs.revoke_all.empty")}
          />
        </div>
      )}
    </div>
  );
}

interface RevokeDeviceFormProps {
  handleSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<RevokeDeviceFormData>;
  setData: SetDataAction<RevokeDeviceFormData>;
  data: RevokeDeviceFormData;
  disabled: boolean;
}

function RevokeDeviceForm(props: RevokeDeviceFormProps): JSX.Element {
  const { t } = useTranslation();
  const { handleSubmit, errors, setData, data, disabled } = props;

  return (
    <form id="revoke-trusted-device-form" onSubmit={handleSubmit} className="grid gap-4">
      <div className="flex flex-col gap-2">
        <Label htmlFor="revoke-trusted-device">
          {t("setting/trusted-device-dialogs.password_confirmation.prompt")}
        </Label>

        <PasswordInput
          id="revoke-trusted-device"
          name="password"
          required
          autoFocus
          placeholder={t("setting/trusted-device-dialogs.password_confirmation.placeholder")}
          disabled={disabled}
          onChange={(event) => {
            setData("password", event.target.value);
          }}
          aria-describedby={errors.password ? "revoke-all-password-error" : undefined}
          aria-invalid={errors.password ? "true" : "false"}
        />

        {errors.password && (
          <InputError aria-live="polite" id="revoke-all-password-error" message={errors.password} />
        )}
      </div>

      <div className="flex gap-2">
        <Checkbox
          id="revoke-trusted-device-terms"
          name="terms"
          checked={data.terms}
          disabled={disabled}
          onCheckedChange={(checked) => {
            setData("terms", checked === true);
          }}
          aria-describedby={errors.terms ? "revoke-all-terms-error" : undefined}
          aria-invalid={errors.terms ? "true" : "false"}
        />

        <LabelForm htmlFor="revoke-trusted-device-terms" className="text-muted-foreground">
          {t("setting/trusted-device-dialogs.revoke_all.terms")}
        </LabelForm>
      </div>

      {errors.terms && (
        <InputError aria-live="polite" id="revoke-all-terms-error" message={errors.terms} />
      )}
    </form>
  );
}

export default TrustedDeviceRevokeAllDialog;
