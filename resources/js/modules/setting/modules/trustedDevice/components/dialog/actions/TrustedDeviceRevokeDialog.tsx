import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { AlertTriangleIcon, Trash2 } from "lucide-react";

import TrustedDeviceSummaryCard from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

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
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2">
              <Trash2 aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Revocar dispositivo
            </h2>
          </DialogTitle>
          <DialogDescription>
            Revocarás {device.name ?? "este dispositivo"}. Dejará de ser de confianza y se te
            volverá a solicitar el código de verificación la próxima vez que inicies sesión desde
            él.
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
              Cancelar
            </Button>
          </DialogClose>

          <Button disabled={processing} form="revoke-trusted-device-form" type="submit">
            {processing ? (
              <>
                <Spinner />
                Revocando...
              </>
            ) : (
              "Revocar dispositivo"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function RevokeConsequencesAlert(): JSX.Element {
  return (
    <Alert
      aria-labelledby="revoke-device-warning-title"
      className={cn(alertVariants.destructive, "my-2")}
    >
      <AlertTriangleIcon aria-hidden="true" />
      <h3
        className="col-start-2 min-h-4 font-medium tracking-tight"
        id="revoke-device-warning-title"
      >
        Consecuencias de la revocación
      </h3>
      <AlertDescription>
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>Este dispositivo ya no estara registrado como de confianza.</li>
          <li>
            Se te pedira el codigo de verificacion la proxima vez que inicies sesion desde este
            dispositivo.
          </li>
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
  const { handleSubmit, errors, setData, data } = props;

  return (
    <form id="revoke-trusted-device-form" className="grid gap-4" onSubmit={handleSubmit}>
      <div className="flex flex-col gap-2">
        <Label htmlFor="revoke-trusted-device">
          Para continuar, confirma y escribe tu contraseña
        </Label>

        <PasswordInput
          aria-describedby={errors.password ? "revoke-device-password-error" : undefined}
          aria-invalid={errors.password ? "true" : "false"}
          autoFocus
          id="revoke-trusted-device"
          name="password"
          placeholder="Ingresa tu contraseña"
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
          Entiendo las consecuencias de revocar este dispositivo.
        </LabelForm>
      </div>

      {errors.terms && (
        <InputError aria-live="polite" id="revoke-device-terms-error" message={errors.terms} />
      )}
    </form>
  );
}

export default TrustedDeviceRevokeDialog;
