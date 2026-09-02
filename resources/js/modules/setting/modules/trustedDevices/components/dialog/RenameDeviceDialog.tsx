import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { Eye, Pencil } from "lucide-react";

import DeviceSummaryCard from "@/modules/setting/modules/trustedDevices/components/ui/DeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevices/utils/inertiaPageProps";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";

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

import { alertVariants, badgeVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

interface RenameDeviceDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
}

interface RenameDeviceFormData {
  name: string | null;
}

function RenameDeviceDialog({ device, open, onClose }: RenameDeviceDialogProps): JSX.Element {
  const { setData, submit, processing, reset, errors, data } = useForm<RenameDeviceFormData>({
    name: device.name,
  });

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

    submit(update({ trustedDevice: device.id }), {
      only: pickReloadKeys(pageProps, ["trustedDevices", "firstTrustedDevice", "recentActivity"]),
      onSuccess: () => {
        onClose();
        reset();
      },
      preserveState: true,
      preserveScroll: true,
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
          <DeviceSummaryCard device={device} lastUsedAt={fromNow(device.lastUsedAt)} />

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
    <Alert className={alertVariants.preview}>
      <Eye />

      <AlertTitle>Vista previa</AlertTitle>
      <AlertDescription>
        Este dispositivo se mostrara como:
        {data.name?.trim() ? (
          <Badge className={cn(badgeVariants.preview, "mt-1 block max-w-full truncate")}>
            {data.name.trim()}
          </Badge>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export default RenameDeviceDialog;
