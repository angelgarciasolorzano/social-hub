import type { JSX } from "react";

import { Head } from "@inertiajs/react";

import {
  ChevronRight,
  Clock4,
  Info,
  MonitorSmartphone,
  Plus,
  ShieldCheck,
  SquarePlus,
} from "lucide-react";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { cn } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";

function TrustedDevice(): JSX.Element {
  return (
    <>
      <Head title="Two Factor Authentication" />
      <div className="flex flex-col gap-4">
        <TrustedDeviceTitle />
        <TrustedDevicesStatCards />
        <TustedDevicesInfoBanner />
      </div>
    </>
  );
}

export default TrustedDevice;

function TrustedDeviceTitle(): JSX.Element {
  return (
    <div className="flex items-center justify-between gap-2 rounded-xl border bg-card p-6 shadow-sm">
      <div className="flex items-start gap-6">
        <div className={cn(iconColorVariants.blue.iconBgClass, "rounded-3xl p-2")}>
          <ShieldCheck className={cn("h-12 w-12", iconColorVariants.blue.iconFgClass)} />
        </div>

        <div className="flex flex-1 flex-col gap-3">
          <div className="flex flex-col gap-1">
            <h2 className="text-xl font-semibold">Dispositivos de confianza</h2>

            <p className="text-sm text-muted-foreground">
              Estos son los dispositivos en los que has iniciado sesion y que has marcado como de
              confianza.
            </p>
          </div>
        </div>
      </div>

      <Button>
        Agregar dispositivo
        <Plus className="h-4 w-4" />
      </Button>
    </div>
  );
}

function TrustedDevicesStatCards(): JSX.Element {
  return (
    <div className="flex items-center gap-4">
      <div className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
        <div className={cn("flex h-14 w-14 rounded-md p-2", iconColorVariants.blue.iconBgClass)}>
          <MonitorSmartphone className={cn("h-10 w-10", iconColorVariants.blue.iconFgClass)} />
        </div>

        <div className="flex w-full items-center justify-between gap-4">
          <div className="space-y-4">
            <h4 className="text-2xl font-medium">5</h4>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-semibold">Total de dispositivos</span>

              <p className="text-sm text-muted-foreground">Dispositvios registrados</p>
            </div>
          </div>
        </div>
      </div>

      <div className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
        <div className={cn("flex h-14 w-14 rounded-md p-2", iconColorVariants.green.iconBgClass)}>
          <ShieldCheck className={cn("h-10 w-10", iconColorVariants.green.iconFgClass)} />
        </div>

        <div className="flex w-full items-center justify-between gap-4">
          <div className="space-y-4">
            <h4 className="text-2xl font-medium">5</h4>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-semibold">Dispositivos activos</span>

              <p className="text-sm text-muted-foreground">Actualmente pueden iniciar sesión</p>
            </div>
          </div>
        </div>
      </div>

      <div className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
        <div className={cn("flex h-14 w-14 rounded-md p-2", iconColorVariants.purple.iconBgClass)}>
          <Clock4 className={cn("h-10 w-10", iconColorVariants.purple.iconFgClass)} />
        </div>

        <div className="flex w-full items-center justify-between gap-4">
          <div className="space-y-4">
            <h4 className="text-2xl font-medium">1</h4>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-semibold">Proximos a expirar</span>

              <p className="text-sm text-muted-foreground">En los proximos 30 días</p>
            </div>
          </div>
        </div>
      </div>

      <div className="flex items-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
        <div className={cn("flex h-14 w-14 rounded-md p-2", iconColorVariants.orange.iconBgClass)}>
          <SquarePlus className={cn("h-10 w-10", iconColorVariants.orange.iconFgClass)} />
        </div>

        <div className="flex w-full items-center justify-between gap-4">
          <div className="space-y-4">
            <h4 className="text-2xl font-medium">1</h4>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-semibold">Agregado recientemente</span>

              <p className="text-sm text-muted-foreground">En los ultimos 7 días</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function TustedDevicesInfoBanner() {
  return (
    <Alert>
      <Info />
      <AlertTitle>
        Los dispositivos de confianza te permiten iniciar sesion mas rapido y mantener tu cuenta
        protegida.
      </AlertTitle>
      <AlertDescription className="flex cursor-pointer items-center font-semibold text-blue-700 hover:underline dark:text-blue-500">
        Mas información sobre dispositivos de confianza <ChevronRight className="h-4 w-4" />
      </AlertDescription>
    </Alert>
  );
}
