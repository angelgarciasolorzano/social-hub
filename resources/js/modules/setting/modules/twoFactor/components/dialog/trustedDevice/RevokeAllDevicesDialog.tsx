import type { JSX, SubmitEvent } from "react";
import { Fragment } from "react";

import { type SetDataAction, useForm } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";
import { MdOutlineLaptopMac } from "react-icons/md";

import type { FormDataErrors } from "@inertiajs/core";
import dayjs from "dayjs";
import { AlertTriangleIcon, CircleAlert, Trash2 } from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/twoFactor/types/trustedDevice";

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
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

interface RevokeAllDevicesDialogProps {
  devices: TrustedDevice[];
  open: boolean;
  onClose: () => void;
}

interface RevokeDeviceFormData {
  password: string;
  terms: boolean;
}

function formatLongDate(iso: string | null): string {
  if (iso === null) {
    return "Nunca";
  }
  return dayjs(iso).format("D [de] MMMM [del] YYYY, h:mm A");
}

function RevokeAllDevicesDialog({
  devices,
  open,
  onClose,
}: RevokeAllDevicesDialogProps): JSX.Element {
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

    console.log("submit revoke all devices form", data);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-3xl min-w-2xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Trash2 className="h-5 w-5 text-muted-foreground" />
              Revocar todos los dispositivos
            </div>
          </DialogTitle>
          <DialogDescription>
            Eliminaras todos los dispositivos de confianza vinculados a tu cuenta. La proxima vez
            que inicies sesion en cualquiera de ellos, se te volvera a solicitar el codigo de
            verificacion.
          </DialogDescription>
        </DialogHeader>

        <RevokeConsequencesAlert />

        <div className="flex flex-col gap-4 rounded-xl border p-4 shadow-xs">
          <div className="flex min-w-0 items-start gap-4">
            <div className="flex h-14 w-14 shrink-0 rounded-full border border-violet-100 bg-violet-100/50 p-2 dark:border-violet-200/10 dark:bg-violet-900/20">
              <MdOutlineLaptopMac className="h-10 w-10 text-violet-700 dark:text-violet-500" />
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
              <span className="font-semibold">3 dispositivos seran revocados</span>
              <p className="text-sm text-muted-foreground">
                Incluye todos los dispositivos de confianza registrados actualmente.
              </p>
            </div>
          </div>

          <ScrollArea className="h-40 rounded-xl border py-3">
            <div>
              {devices.map((device, index) => (
                <Fragment key={device.id}>
                  <div className="mx-4 flex items-center gap-2 text-sm">
                    <MdOutlineLaptopMac className="shrink-0" />

                    <span className="max-w-20 truncate font-medium">{device.name}</span>

                    <FaCircle className="h-1 w-1 text-muted-foreground" />

                    <span className="text-muted-foreground">{device.browser}</span>

                    <FaCircle className="h-1 w-1 text-muted-foreground" />

                    <span className="truncate text-muted-foreground">
                      Expira el {formatLongDate(device.expiresAt)}
                    </span>
                  </div>

                  {index < devices.length - 1 && <Separator className="my-2" />}
                </Fragment>
              ))}
            </div>
          </ScrollArea>
        </div>

        <RevokeDeviceForm
          handleSubmit={handleSubmit}
          errors={errors}
          setData={setData}
          data={data}
        />

        <DialogFooter className="flex items-center gap-4 border-t sm:justify-between">
          <Alert className="border-none bg-transparent">
            <CircleAlert className="text-purple-500 dark:text-purple-500" />
            <AlertTitle className="line-clamp-3 font-normal text-muted-foreground">
              Si solo deseas eliminar uno, puedes revocarlo{" "}
              <strong className="text-purple-700 dark:text-purple-500">individualmente</strong>{" "}
              desde la lista de dispositivos.
            </AlertTitle>
          </Alert>

          <div className="flex items-center justify-center gap-2">
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
                "Revokar todos"
              )}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function RevokeConsequencesAlert(): JSX.Element {
  return (
    <Alert className="border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-500">
      <AlertTriangleIcon />
      <AlertTitle>¿Que pasara?</AlertTitle>
      <AlertDescription>
        <ul className="mt-1 list-inside list-disc space-y-2">
          <li>Se eliminaran todos los dispositivos de confianza vinculados a tu cuenta.</li>
          <li>Se te volvera a solicitar el codigo de verificacion (2FA) en esos dispositivos.</li>
          <li>Esta acción no se puede deshacer.</li>
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

        <LabelForm htmlFor="revoke-trusted-device-terms" className="text-muted-foreground">
          Entiendo que esta accion eliminara todos mis dispositivos de confianza.
        </LabelForm>
      </div>

      {errors.terms && <InputError message={errors.terms} />}
    </form>
  );
}

export default RevokeAllDevicesDialog;
