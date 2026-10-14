import { type JSX, type SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { Link, useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { CircleAlert, ShieldAlert, ShieldPlus } from "lucide-react";

import TrustedDeviceOtpField from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceOtpField";
import type {
  TwoFactorRequiredErrors,
  TwoFactorStatus,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type { TrustedDevicePreview } from "@/modules/setting/modules/trustedDevice/types/trustedDevicePreview";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import { valueOrFallback } from "@/modules/setting/modules/trustedDevice/utils/valueOrFallback";
import { formatShortDate, fromNow, valueOrNow } from "@/modules/setting/shared/utils/dateTime";
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
              Agregar dispositivo de confianza
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Este navegador aún no está registrado como dispositivo de confianza. Al agregarlo, no se
            te solicitará el código de verificación desde este navegador hasta su fecha de
            expiración. Es una comodidad temporal para omitir ese código, no un segundo factor de
            autenticación independiente.
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
              Agrégalo solo si es tu equipo personal. Puedes revocarlo cuando quieras desde esta
              pantalla.
            </AlertDescription>
          </Alert>
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          {twoFactorStatus === "confirmed" ? (
            <Button type="submit" form="add-trusted-device-form" disabled={processing}>
              {processing ? (
                <>
                  <Spinner />
                  Agregando...
                </>
              ) : (
                "Agregar dispositivo"
              )}
            </Button>
          ) : (
            <Button asChild>
              <Link href={twoFactorAuthenticationIndex.url()}>
                Ir a autenticación de dos factores
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
  const browser = valueOrFallback(
    [preview?.browser, preview?.browserVersion].filter((value) => value !== "").join(" "),
    "Desconocido",
  );

  const osName = valueOrFallback(preview?.osName, "Desconocido");
  const lastUsedAt = valueOrNow(preview?.lastUsedAt);
  const expiresAt = valueOrNow(preview?.expiresAt);
  const deviceDescription =
    [browser, osName].filter((value) => value !== "Desconocido").join(" en ") || "Desconocido";
  const previewDeviceSource = preview ?? { osName: null, isMobile: false };

  const metadataFields: DevicePreviewMetadataField[] = [
    {
      label: "Navegador",
      value: browser,
      className: "border-r border-b border-border sm:border-b-0",
    },
    {
      label: "Sistema",
      value: osName,
      className: "border-b border-border sm:border-r sm:border-b-0",
    },
    {
      label: "Último acceso",
      value: fromNow(lastUsedAt),
      className: "border-r border-border",
    },
    {
      label: "Expira",
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
            <CardTitle className="text-base">Dispositivo detectado</CardTitle>

            <CardDescription className="mt-1 truncate">{deviceDescription}</CardDescription>
          </div>
        </div>

        <Badge
          className={cn(badgeVariants.amber, "gap-1.5 border-amber-200 dark:border-amber-500")}
        >
          <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
          Sin registrar
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
  return (
    <form id="add-trusted-device-form" onSubmit={handleSubmit} className="flex flex-col gap-4">
      <div className="flex flex-col gap-2">
        <LabelForm htmlFor="add-device-name" error={errors.name}>
          Nombre del dispositivo (opcional)
        </LabelForm>

        <Input
          id="add-device-name"
          name="name"
          placeholder="Mi dispositivo"
          aria-describedby={errors.name ? "add-device-name-error" : undefined}
          onChange={(event) => {
            setData("name", event.target.value);
          }}
          aria-invalid={errors.name !== undefined ? "true" : "false"}
        />

        <InputError aria-live="polite" id="add-device-name-error" message={errors.name} />
      </div>

      <TrustedDeviceOtpField
        id="add-device-otp"
        label="Código de verificación"
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
  const isPending = twoFactorStatus === "pending";

  return (
    <Alert className={alertVariants.info}>
      <ShieldAlert aria-hidden="true" />
      <AlertTitle>
        {isPending
          ? "Falta confirmar la autenticación de dos factores"
          : "Primero activa la autenticación de dos factores"}
      </AlertTitle>
      <AlertDescription>
        {isPending
          ? "Ya iniciaste la configuración, pero aún no ingresaste el código de tu aplicación autenticadora. Confírmala para poder agregar dispositivos de confianza."
          : "Para agregar un dispositivo de confianza necesitas tener la autenticación de dos factores activada y confirmada. Actívala desde la configuración de seguridad."}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceAddDialog;
