import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import { CalendarRange, CircleAlert, Clock, Globe, Monitor, ShieldPlus } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/shadcn/ui/card";
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
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { alertVariants } from "@/shared/lib/styling/alertVariants";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling/iconColorVariants";
import { cn } from "@/shared/lib/utils";

import type { DevicePreview } from "../../../types/devicePreview";
import { formatLongDate, fromNow } from "../../../utils/dateTime";
import { valueOrFallback } from "../../../utils/valueOrFallback";

interface AddDeviceDialogProps {
  preview: DevicePreview | null;
  open: boolean;
  onClose: () => void;
}

interface AddDeviceFormData {
  name: string;
}

function AddDeviceDialog({ preview, open, onClose }: AddDeviceDialogProps): JSX.Element {
  const { setData, submit, processing, reset, errors } = useForm<AddDeviceFormData>({
    name: "",
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

    submit(store(), {
      only: ["trustedDevices"],
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-3xl min-w-2xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldPlus className="h-5 w-5 text-muted-foreground" />
              Agregar dispositivo de confianza
            </div>
          </DialogTitle>
          <DialogDescription>
            Este navegador aun no esta registrado como dispositivo de confianza. Al agregarlo, no se
            te solicitara el codigo de verificacion desde este navegador hasta su fecha de
            expiracion.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <Alert className={alertVariants.info}>
            <CircleAlert />

            <AlertTitle className="line-clamp-4">Consejo</AlertTitle>

            <AlertDescription>
              Te recomendamos usar un nombre que te ayude a reconocer este dispositivo facilmente.
              Este nombre solo lo veras tu.
            </AlertDescription>
          </Alert>

          <DevicePreviewInfo preview={preview} />

          <AddDeviceForm handleSubmit={handleSubmit} errors={errors} setData={setData} />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              Cancelar
            </Button>
          </DialogClose>

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
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type DevicePreviewInfoProps = Pick<AddDeviceDialogProps, "preview">;

function DevicePreviewInfo({ preview }: DevicePreviewInfoProps): JSX.Element {
  const browser = valueOrFallback(preview?.browser, "Desconocido");
  const osName = valueOrFallback(preview?.osName, "Desconocido");
  const lastUsedAt = preview?.lastUsedAt ?? new Date().toISOString();
  const expiresAt = preview?.expiresAt ?? new Date().toISOString();

  return (
    <Card className="dark:bg-input/10">
      <CardHeader>
        <CardTitle>Dispositivo detectado</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr] gap-4">
        <PreviewCard icon={Globe} iconColor="green" title="Navegador" value={browser} />

        <Separator orientation="vertical" />

        <PreviewCard icon={Monitor} iconColor="blue" title="Sistema operativo" value={osName} />
        <Separator orientation="vertical" />

        <PreviewCard
          icon={Clock}
          iconColor="yellow"
          title="Ultimo acceso"
          value={fromNow(lastUsedAt)}
        />
        <Separator orientation="vertical" />

        <PreviewCard
          icon={CalendarRange}
          iconColor="green"
          title="Expira el"
          value={formatLongDate(expiresAt)}
        />
      </CardContent>
    </Card>
  );
}

interface PreviewCardProps {
  icon: typeof Globe;
  iconColor: IconColorVariant;
  title: string;
  value: string;
}

function PreviewCard({ icon: Icon, iconColor, title, value }: PreviewCardProps): JSX.Element {
  const colors = iconColorVariants[iconColor];

  return (
    <div className="flex flex-col items-center gap-4 text-center">
      <div className={cn("flex h-10 w-10 rounded-md p-2", colors.iconBgClass)}>
        <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
      </div>

      <div className="flex flex-col items-center gap-1">
        <span className="text-sm font-medium">{title}</span>

        <span className="text-sm text-muted-foreground">{value}</span>
      </div>
    </div>
  );
}

interface AddDeviceFormProps {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<AddDeviceFormData>;
  setData: SetDataAction<AddDeviceFormData>;
}

function AddDeviceForm({ handleSubmit, errors, setData }: AddDeviceFormProps): JSX.Element {
  return (
    <form id="add-trusted-device-form" onSubmit={handleSubmit} className="mt-2 flex flex-col gap-2">
      <LabelForm htmlFor="add-device-name" error={errors.name}>
        Dale un nombre a este dispositivo (opcional)
      </LabelForm>

      <p className="text-sm text-muted-foreground">
        Asi podras identificarlo facilmente si tienes varios dispositivos registrados.
      </p>

      <Input
        id="add-device-name"
        name="name"
        placeholder="Mi dispositivo"
        onChange={(e) => {
          setData("name", e.target.value);
        }}
        aria-invalid={errors.name !== undefined ? "true" : "false"}
      />

      <InputError message={errors.name} />
    </form>
  );
}

export default AddDeviceDialog;
