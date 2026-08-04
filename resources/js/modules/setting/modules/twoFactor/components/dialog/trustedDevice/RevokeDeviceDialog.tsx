import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import dayjs from "dayjs";
import { AlertTriangleIcon, Trash2 } from "lucide-react";

import { destroy } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import type { TrustedDevice } from "../../../types/trustedDevice";
import DeviceSummaryCard from "../../ui/DeviceSummaryCard";

interface RevokeDeviceDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

const fromNow = (iso: string | null): string => {
  if (iso === null) {
    return "nunca";
  }

  return dayjs(iso).fromNow();
};

function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }
  return dayjs(iso).format("D [de] MMMM [del] YYYY, h:mm A");
}

interface RevokeDeviceFormData {
  password: string;
  terms: boolean;
}

function RevokeDeviceDialog({ device, open, onClose }: RevokeDeviceDialogProps): JSX.Element {
  const { data, setData, submit, processing, reset, errors } = useForm<RevokeDeviceFormData>({
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

  const handleSubmit = (event: SubmitEvent<HTMLFormElement>) => {
    event.preventDefault();

    submit(destroy({ trustedDevice: device.id }), {
      only: ["trustedDevices", "trustedDevicesCount"],
      onSuccess: () => {
        onClose();
        reset();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Trash2 className="h-5 w-5 text-muted-foreground" />
              Revokar dispositivo
            </div>
          </DialogTitle>
          <DialogDescription>
            Eliminaras este dispositivo de confianza. Se te volvera a solicitar el codigo de
            verificacion al iniciasr sesion.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <DeviceSummaryCard
            device={device}
            lastUsedAt={fromNow(device.lastUsedAt)}
            expiration={formatLongDate(device.expiresAt)}
          />

          <RevokeConsequencesAlert />

          <RevokeDeviceForm
            handleSubmit={handleSubmit}
            errors={errors}
            setData={setData}
            data={data}
          />
        </div>

        <DialogFooter className="mt-1.5">
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form="revoke-trusted-device-form" disabled={processing}>
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
    <Alert className="my-2 border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-500">
      <AlertTriangleIcon />
      <AlertTitle>¿Que pasara?</AlertTitle>
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
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<RevokeDeviceFormData>;
  setData: SetDataAction<RevokeDeviceFormData>;
  data: RevokeDeviceFormData;
}

function RevokeDeviceForm(props: RevokeDeviceFormProps): JSX.Element {
  const { handleSubmit, errors, setData, data } = props;

  return (
    <form id="revoke-trusted-device-form" onSubmit={handleSubmit} className="grid gap-4">
      <div className="flex flex-col gap-2">
        <Label htmlFor="revoke-trusted-device">
          Para continuar, confirma y escribe tu contraseña
        </Label>

        <PasswordInput
          id="revoke-trusted-device"
          name="password"
          required
          autoFocus
          placeholder="Ingresa tu contraseña"
          onChange={(e) => {
            setData("password", e.target.value);
          }}
          aria-invalid={errors.password ? "true" : "false"}
        />

        {errors.password && <InputError message={errors.password} />}
      </div>

      <div className="flex gap-2">
        <Checkbox
          id="revoke-trusted-device-terms"
          name="terms"
          checked={data.terms}
          onCheckedChange={(checked) => {
            setData("terms", checked === true);
          }}
          aria-invalid={errors.terms ? "true" : "false"}
        />

        <LabelForm htmlFor="revoke-trusted-device-terms">
          Entiendo las consecuencias de revocar este dispositivo.
        </LabelForm>
      </div>

      {errors.terms && <InputError message={errors.terms} />}
    </form>
  );
}

export default RevokeDeviceDialog;
