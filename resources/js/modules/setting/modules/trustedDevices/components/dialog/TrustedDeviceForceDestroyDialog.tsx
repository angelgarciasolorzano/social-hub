import type { JSX, SubmitEvent } from "react";

import { useForm } from "@inertiajs/react";

import { AlertTriangleIcon, Trash2 } from "lucide-react";

import DeviceSummaryCard from "@/modules/setting/modules/trustedDevices/components/ui/DeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

import { forceDestroy } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Trash2 className="h-5 w-5 text-muted-foreground" />
              Eliminar permanentemente
            </div>
          </DialogTitle>
          <DialogDescription>
            Esta acción es definitiva. El dispositivo se eliminara por completo de tu cuenta y no
            podras volver a confiar en el.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <DeviceSummaryCard
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
              Cancelar
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
                Eliminando...
              </>
            ) : (
              "Eliminar definitivamente"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ForceDestroyConsequencesAlert(): JSX.Element {
  return (
    <Alert className={alertVariants.destructive}>
      <AlertTriangleIcon />
      <AlertTitle>¿Qué pasará?</AlertTitle>
      <AlertDescription>
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>Este dispositivo se eliminará permanentemente de la base de datos.</li>
          <li>Ya no podrás reactivarlo desde la sección &quot;Revocados&quot;.</li>
          <li>
            Tu historial de actividad seguirá mostrando las acciones realizadas con este
            dispositivo, pero ya no permitirá abrirlas para ver los detalles.
          </li>
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
  return (
    <form id="force-destroy-trusted-device-form" onSubmit={handleSubmit} className="grid gap-4">
      <div className="flex flex-col gap-2">
        <Label htmlFor="force-destroy-trusted-device">
          Para continuar, confirma y escribe tu contraseña
        </Label>

        <PasswordInput
          id="force-destroy-trusted-device"
          name="password"
          required
          autoFocus
          placeholder="Ingresa tu contraseña"
          onChange={(event) => {
            setData("password", event.target.value);
          }}
          aria-invalid={errors.password ? "true" : "false"}
        />

        {errors.password && <InputError message={errors.password} />}
      </div>

      <div className="flex gap-2">
        <Checkbox
          id="force-destroy-trusted-device-terms"
          name="terms"
          checked={data.terms}
          onCheckedChange={(checked) => {
            setData("terms", checked === true);
          }}
          aria-invalid={errors.terms ? "true" : "false"}
        />

        <LabelForm htmlFor="force-destroy-trusted-device-terms">
          Entiendo que esta acción es irreversible.
        </LabelForm>
      </div>

      {errors.terms && <InputError message={errors.terms} />}
    </form>
  );
}

export default TrustedDeviceForceDestroyDialog;
