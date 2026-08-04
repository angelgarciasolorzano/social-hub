import type { JSX, SubmitEvent } from "react";

import type { SetDataAction } from "@inertiajs/react";
import { useForm } from "@inertiajs/react";

import type { FormDataErrors } from "@inertiajs/core";
import {
  CalendarRange,
  CircleAlert,
  Globe,
  Lock,
  MapPin,
  Monitor,
  ShieldCheck,
  ShieldPlus,
} from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

import { InputError, LabelForm } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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
import { Separator } from "@/shared/components/shadcn/ui/separator";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { alertVariants } from "@/shared/lib/styling/alertVariants";

import type { DevicePreview } from "../../../types/devicePreview";
import { formatLongDate } from "../../../utils/dateTime";
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
      <DialogContent className="max-w-5xl min-w-4xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldPlus className="h-5 w-5 text-muted-foreground" />
              Agregar dispositivo de confianza
            </div>
          </DialogTitle>
          <DialogDescription>
            Este dispositivo no esta registrado como de confianza. Puedes agregarlo para evitar el
            codigo de verificacion en el futuro.
          </DialogDescription>
        </DialogHeader>

        <div className="no-scrollbar -mx-4 max-h-[75vh] space-y-4 overflow-y-auto px-4">
          <AddDeviceBenefits />

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

function AddDeviceBenefits(): JSX.Element {
  return (
    <Card className="border-violet-200 bg-violet-300/5 dark:border-violet-500/30 dark:bg-violet-900/5">
      <CardHeader>
        <CardTitle className="text-purple-900 dark:text-purple-400">
          ¿Que significa agregar este dispositivo?
        </CardTitle>
        <CardDescription>
          Al agregar este dispositivo de confianza, podras iniciar sesion sin necesidad de ingresar
          el codigo de verificacion cada vez, hasta su fecha de expiracion.
        </CardDescription>
      </CardHeader>
      <CardContent className="grid grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-4">
        <div className="flex gap-4">
          <div className="flex h-12 w-12 shrink-0 rounded-md bg-purple-100/50 p-2 dark:bg-purple-900/20">
            <Lock className="h-8 w-8 text-purple-700 dark:text-purple-500" />
          </div>

          <div className="flex flex-1 flex-col gap-0.5">
            <h4 className="text-sm font-medium">Inicio de sesion mas rapido</h4>

            <p className="text-sm text-muted-foreground">
              No tendras que ingresar el codigo de verificacion cada vez.
            </p>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div className="flex gap-4">
          <div className="flex h-12 w-12 shrink-0 rounded-md bg-purple-100/50 p-2 dark:bg-purple-900/20">
            <CalendarRange className="h-8 w-8 text-purple-700 dark:text-purple-500" />
          </div>

          <div className="flex flex-1 flex-col gap-0.5">
            <h4 className="text-sm font-medium">Seguridad bajo tu control</h4>

            <p className="text-sm text-muted-foreground">
              Puedes renovar o revocar la confianza en cualquier momento.
            </p>
          </div>
        </div>

        <Separator orientation="vertical" />

        <div className="flex gap-4">
          <div className="flex h-12 w-12 shrink-0 rounded-md bg-purple-100/50 p-2 dark:bg-purple-900/20">
            <ShieldCheck className="h-8 w-8 text-purple-700 dark:text-purple-500" />
          </div>

          <div className="flex flex-1 flex-col gap-0.5">
            <h4 className="text-sm font-medium">Protege tu cuenta</h4>

            <p className="text-sm text-muted-foreground">
              Solo agrega dispositivos que sean personales y seguros.
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

type DevicePreviewInfoProps = Pick<AddDeviceDialogProps, "preview">;

function DevicePreviewInfo({ preview }: DevicePreviewInfoProps): JSX.Element {
  const browser = valueOrFallback(preview?.browser, "Desconocido");
  const osName = valueOrFallback(preview?.osName, "Desconocido");
  const ip = valueOrFallback(preview?.ip, "No disponible");
  const lastUsedAt = preview?.lastUsedAt ?? new Date().toISOString();

  return (
    <Card>
      <CardHeader>
        <CardTitle>Informacion del dispositivo</CardTitle>
        <CardDescription>
          Este dispositivo sera agregado con la siguiente informacion:
        </CardDescription>
      </CardHeader>
      <CardContent className="flex items-stretch gap-4">
        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/20">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <Globe className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Navegador</span>

            <span className="text-sm text-muted-foreground">{browser}</span>
          </div>
        </div>

        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/20">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <Monitor className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Sistema operativo</span>

            <span className="text-sm text-muted-foreground">{osName}</span>
          </div>
        </div>

        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/20">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <MapPin className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Direccion IP</span>

            <span className="text-sm text-muted-foreground">{ip}</span>
          </div>
        </div>

        <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/20">
          <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
            <MapPin className="h-6 w-6 text-violet-700 dark:text-violet-500" />
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium">Ultimo acceso</span>

            <span className="text-sm text-muted-foreground">Ahora</span>

            <span className="text-sm text-muted-foreground">{formatLongDate(lastUsedAt)}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

interface AddDeviceFormProps {
  handleSubmit: (e: SubmitEvent<HTMLFormElement>) => void;
  errors: FormDataErrors<AddDeviceFormData>;
  setData: SetDataAction<AddDeviceFormData>;
}

function AddDeviceForm({ handleSubmit, errors, setData }: AddDeviceFormProps): JSX.Element {
  return (
    <form
      id="add-trusted-device-form"
      onSubmit={handleSubmit}
      className="grid grid-cols-[1.3fr_1fr] gap-6 rounded-xl border p-4 shadow-xs"
    >
      <div className="flex flex-col gap-2">
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
      </div>

      <Alert className={alertVariants.info}>
        <CircleAlert />

        <AlertTitle className="line-clamp-4">Consejo</AlertTitle>

        <AlertDescription>
          Te recomendamos usar un nombre que te ayude a reconocer este dispositivo facilmente. Este
          nombre solo lo veras tu.
        </AlertDescription>
      </Alert>
    </form>
  );
}

export default AddDeviceDialog;
