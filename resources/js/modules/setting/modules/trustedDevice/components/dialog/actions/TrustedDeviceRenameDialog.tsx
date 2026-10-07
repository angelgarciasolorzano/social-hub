import { type JSX, type SubmitEvent, useRef } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm, usePage } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { Eye, Pencil } from "lucide-react";

import TrustedDeviceSummaryCard from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import { pickReloadKeys } from "@/modules/setting/modules/trustedDevice/utils/inertiaPageProps";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";

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

interface TrustedDeviceRenameDialogProps {
  device: TrustedDevice;
  open: boolean;
  onClose: () => void;
  returnFocusTarget?: HTMLElement | null;
}

interface RenameDeviceFormData {
  name: string | null;
}

function TrustedDeviceRenameDialog({
  device,
  open,
  onClose,
  returnFocusTarget,
}: TrustedDeviceRenameDialogProps): JSX.Element {
  const returnFocusRef = useRef<HTMLElement | null>(null);

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
      <DialogContent
        onOpenAutoFocus={() => {
          const activeElement = document.activeElement;

          returnFocusRef.current =
            activeElement instanceof HTMLElement && activeElement !== document.body
              ? activeElement
              : (returnFocusTarget ?? null);
        }}
        onCloseAutoFocus={(event) => {
          const capturedTarget = returnFocusRef.current;
          const focusTarget = capturedTarget?.isConnected ? capturedTarget : returnFocusTarget;

          if (focusTarget?.isConnected) {
            event.preventDefault();
            focusTarget.focus();
          }

          returnFocusRef.current = null;
        }}
      >
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Pencil aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Renombrar Dispositivo
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Asigna un nombre personalizado para identificar este dispositivo facilmente.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <TrustedDeviceSummaryCard
            device={device}
            lastUsedAt={fromNow(device.lastUsedAt)}
            expiration={formatLongDate(device.expiresAt)}
          />

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
        aria-describedby={errors.name ? "rename-trusted-device-error" : "rename-device-description"}
        aria-invalid={errors.name ? "true" : "false"}
      />

      {errors.name && (
        <InputError aria-live="polite" id="rename-trusted-device-error" message={errors.name} />
      )}

      {!errors.name && (
        <p className="text-sm font-normal text-muted-foreground" id="rename-device-description">
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
      <AlertDescription className="text-sm font-normal">
        Este dispositivo se mostrara como:
        {data.name?.trim() ? (
          <Badge
            className={cn(
              badgeVariants.preview,
              "mt-1 block max-w-full truncate text-sm font-medium",
            )}
          >
            {data.name.trim()}
          </Badge>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export default TrustedDeviceRenameDialog;
