import type { JSX } from "react";

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

interface AddDeviceDialogProps {
  open: boolean;
  onClose: () => void;
}

function AddDeviceDialog({ open, onClose }: AddDeviceDialogProps): JSX.Element {
  const handleOpenChange = (nextOpen: boolean): void => {
    if (nextOpen) {
      return;
    }

    onClose();
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
          <Card className="border-violet-200 bg-violet-300/5 dark:border-violet-500/30 dark:bg-violet-900/5">
            <CardHeader>
              <CardTitle className="text-purple-900 dark:text-purple-400">
                ¿Que significa agregar este dispositivo?
              </CardTitle>
              <CardDescription>
                Al agregar este dispositivo de confianza, podras iniciar sesion sin necesidad de
                ingresar el codigo de verificacion cada vez, hasta su fecha de expiracion.
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

          <Card>
            <CardHeader>
              <CardTitle>Informacion del dispositivo</CardTitle>
              <CardDescription>
                Este dispositivo sera agregado con la siguiente informacion:
              </CardDescription>
            </CardHeader>
            <CardContent className="flex items-stretch gap-4">
              <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
                <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
                  <Globe className="h-6 w-6 text-violet-700 dark:text-violet-500" />
                </div>

                <div className="flex flex-col gap-1">
                  <span className="text-sm font-medium">Navegador</span>

                  <span className="text-sm text-muted-foreground">Chrome 149</span>
                </div>
              </div>

              <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
                <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
                  <Monitor className="h-6 w-6 text-violet-700 dark:text-violet-500" />
                </div>

                <div className="flex flex-col gap-1">
                  <span className="text-sm font-medium">Sistema operativo</span>

                  <span className="text-sm text-muted-foreground">Mac</span>
                </div>
              </div>

              <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
                <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
                  <MapPin className="h-6 w-6 text-violet-700 dark:text-violet-500" />
                </div>

                <div className="flex flex-col gap-1">
                  <span className="text-sm font-medium">Direccion IP</span>

                  <span className="text-sm text-muted-foreground">192.168.1.1</span>
                </div>
              </div>

              <div className="flex gap-4 rounded-xl border p-3 shadow-xs dark:bg-input/25">
                <div className="flex h-10 w-10 rounded-md bg-violet-100/50 p-2 dark:bg-violet-900/20">
                  <MapPin className="h-6 w-6 text-violet-700 dark:text-violet-500" />
                </div>

                <div className="flex flex-col gap-1">
                  <span className="text-sm font-medium">Ultimo acceso</span>

                  <span className="text-sm text-muted-foreground">Ahora</span>

                  <span className="text-sm text-muted-foreground">
                    2 de agosto del 2026, 9:37 AM
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="grid grid-cols-[1.3fr_1fr] gap-6">
              <div className="flex flex-col gap-2">
                <span className="font-semibold">Dale un nombre a este dispositivo (opcional)</span>
                <p className="text-sm text-muted-foreground">
                  Asi podras identificarlo facilmente si tienes varios dispositivos registrados.
                </p>
                <Input />
              </div>

              <Alert className="border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
                <CircleAlert />

                <AlertTitle className="line-clamp-4">Consejo</AlertTitle>

                <AlertDescription>
                  Te recomendamos usar un nombre que te ayude a reconocer este dispositivo
                  facilmente. Este nombre solo lo veras tu.
                </AlertDescription>
              </Alert>
            </CardContent>
          </Card>
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit">Agregar dispositivo</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default AddDeviceDialog;
