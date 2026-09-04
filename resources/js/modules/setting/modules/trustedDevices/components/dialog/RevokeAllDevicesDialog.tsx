import type { JSX, SubmitEvent } from "react";
import { Fragment } from "react";

import { type SetDataAction, useForm, usePage } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";

import type { FormDataErrors } from "@inertiajs/core";
import {
  AlertTriangleIcon,
  CircleAlert,
  MonitorOff,
  MonitorSmartphone,
  Trash2,
} from "lucide-react";

import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevices/utils/inertiaPageProps";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { formatLongDate } from "@/modules/setting/shared/utils/dateTime";
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
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { alertVariants, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface RevokeAllDevicesDialogProps {
  devices: TrustedDevice[];
  open: boolean;
  onClose: () => void;
}

interface RevokeDeviceFormData {
  password: string;
  terms: boolean;
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

            <Button
              type="submit"
              form="revoke-trusted-device-form"
              disabled={processing || devices.length === 0}
            >
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
    <Alert className={alertVariants.destructive}>
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

type AffectedDevicesListProps = Pick<RevokeAllDevicesDialogProps, "devices">;

function AffectedDevicesList({ devices }: AffectedDevicesListProps): JSX.Element {
  const deviceCount = devices.length;
  const hasDevices = deviceCount > 0;
  const headline = hasDevices
    ? `${deviceCount} ${deviceCount === 1 ? "dispositivo sera revocado" : "dispositivos seran revocados"}`
    : "No hay dispositivos para revocar";
  const description = hasDevices
    ? "Incluye todos los dispositivos de confianza registrados actualmente."
    : "Cuando registres un nuevo dispositivo de confianza, aparecera aqui para que puedas revocarlo junto con los demas.";

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
          <span className="font-semibold">{headline}</span>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
      </div>

      {hasDevices ? (
        <ScrollArea className="h-40 rounded-xl border py-3 dark:bg-input/10">
          {devices.map((device, index) => (
            <Fragment key={device.id}>
              <div className="mx-4 flex items-center gap-2 text-sm">
                {getDeviceIcon(device, "shrink-0")}

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
        </ScrollArea>
      ) : (
        <div className="flex h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-sm text-muted-foreground dark:bg-input/10">
          <EmptyState
            icon={MonitorOff}
            title="Aun no tienes dispositivos de confianza registrados."
          />
        </div>
      )}
    </div>
  );
}

interface RevokeDeviceFormProps {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<RevokeDeviceFormData>;
  setData: SetDataAction<RevokeDeviceFormData>;
  data: RevokeDeviceFormData;
  disabled: boolean;
}

function RevokeDeviceForm(props: RevokeDeviceFormProps): JSX.Element {
  const { handleSubmit, errors, setData, data, disabled } = props;

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
          disabled={disabled}
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
          disabled={disabled}
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
