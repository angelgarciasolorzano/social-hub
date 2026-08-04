import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";
import { MdOutlineLaptopMac } from "react-icons/md";

import type { FormDataErrors } from "@inertiajs/core";
import dayjs from "dayjs";
import { Eye, Pencil } from "lucide-react";

import { update } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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
import { Input } from "@/shared/components/shadcn/ui/input";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import type { TrustedDevice } from "../../../types/trustedDevice";

interface RenameDeviceDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface RenameDeviceFormData {
  name: string | null;
}

const deviceLabel = (device: TrustedDevice): string => {
  return device.name ?? device.userAgent ?? "Dispositivo desconocido";
};

const fromNow = (iso: string | null): string => {
  if (iso === null) {
    return "nunca";
  }

  return dayjs(iso).fromNow();
};

function RenameDeviceDialog({ device, open, onClose }: RenameDeviceDialogProps): JSX.Element {
  const { setData, submit, processing, reset, errors, data } = useForm<RenameDeviceFormData>({
    name: device.name,
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

    submit(update({ trustedDevice: device.id }), {
      only: ["trustedDevices"],
      onSuccess: () => {
        onClose();
        reset();
      },
      preserveState: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <Pencil className="h-5 w-5 text-muted-foreground" />
              Renombrar Dispositivo
            </div>
          </DialogTitle>
          <DialogDescription>
            Asigna un nombre personalizado para identificar este dispositivo facilmente.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <DeviceInfoCard device={device} />

          <RenameDeviceForm handleSubmit={handleSubmit} errors={errors} setData={setData} />

          <DeviceNamePreview data={data} />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" form="rename-trusted-device-form" disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                Guardando cambios...
              </>
            ) : (
              "Guardar cambios"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DeviceInfoCardProps = Pick<RenameDeviceDialogProps, "device">;

function DeviceInfoCard({ device }: DeviceInfoCardProps): JSX.Element {
  return (
    <div className="flex min-w-0 items-start gap-2.5 rounded-xl border p-4 shadow-xs dark:dark:bg-input/10">
      <div className="flex h-14 w-14 shrink-0 rounded-md border border-violet-100 bg-violet-100/50 p-2 dark:border-violet-200/10 dark:bg-violet-900/20">
        <MdOutlineLaptopMac className="h-10 w-10 text-violet-700 dark:text-violet-500" />
      </div>

      <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
        <span className="block truncate font-medium" title={deviceLabel(device)}>
          {deviceLabel(device)}
        </span>

        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          {device.browser !== null && device.browser !== "" && (
            <span className="truncate text-sm text-muted-foreground">
              {device.osName} - {device.browser}
            </span>
          )}

          <FaCircle className="h-1 w-1 shrink-0" />

          <span className="truncate text-sm text-muted-foreground">
            Último uso: {fromNow(device.lastUsedAt)}
          </span>
        </div>
      </div>
    </div>
  );
}

interface RenameDeviceFormProps {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<RenameDeviceFormData>;
  setData: SetDataAction<RenameDeviceFormData>;
}

function RenameDeviceForm({ handleSubmit, errors, setData }: RenameDeviceFormProps): JSX.Element {
  return (
    <form id="rename-trusted-device-form" onSubmit={handleSubmit} className="grid gap-2">
      <LabelForm htmlFor="rename-trusted-device" error={errors.name}>
        Nombre del dispositivo
      </LabelForm>

      <Input
        id="rename-trusted-device"
        name="name"
        required
        autoFocus
        placeholder="Renombrar dispositivo"
        onChange={(e) => {
          setData("name", e.target.value);
        }}
        aria-invalid={errors.name ? "true" : "false"}
      />

      {errors.name && <InputError message={errors.name} />}

      {!errors.name && (
        <p className="text-sm text-muted-foreground">
          Este sera el nombre con el que identificaras este dispositivo.
        </p>
      )}
    </form>
  );
}

interface DeviceNamePreviewProps {
  data: RenameDeviceFormData;
}

function DeviceNamePreview({ data }: DeviceNamePreviewProps): JSX.Element {
  return (
    <Alert className="border-purple-200 bg-purple-50 text-purple-900 dark:border-purple-500/30 dark:bg-purple-500/10 dark:text-purple-400">
      <Eye />

      <AlertTitle>Vista previa</AlertTitle>
      <AlertDescription>
        Este dispositivo se mostrara como:
        {data.name?.trim() ? (
          <Badge className="mt-1 block max-w-full truncate bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
            {data.name.trim()}
          </Badge>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export default RenameDeviceDialog;
