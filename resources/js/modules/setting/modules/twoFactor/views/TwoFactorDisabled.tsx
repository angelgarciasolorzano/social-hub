import type { JSX } from "react";

import { Form } from "@inertiajs/react";

import {
  CircleCheck,
  Info,
  LockKeyhole,
  ShieldAlert,
  ShieldCheck,
  SquareArrowOutUpRight,
} from "lucide-react";
import { toast } from "sonner";

import { enable } from "@/shared/wayfinder/routes/two-factor";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";

import { useAppearance } from "@/shared/hooks";

import { alertVariants, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import { OptionCard } from "../components/ui/OptionCard";
import SummaryCard from "../components/ui/SummaryCard";
import Timeline from "../components/ui/Timeline";
import {
  twoFactorBenefits,
  twoFactorImportantDetails,
  twoFactorOperationSteps,
  twoFactorRecommendedApps,
  twoFactorRequirements,
} from "../data/twoFactorDisabled";

interface TwoFactorDisabledProps {
  hasSetupData: boolean;
  onActivate: () => void;
}

function TwoFactorDisabled({ hasSetupData, onActivate }: TwoFactorDisabledProps): JSX.Element {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex min-w-0 flex-col gap-6 xl:flex-row">
        <div className="flex min-w-0 flex-1 flex-col gap-6 xl:gap-8">
          <TwoFactorTitle hasSetupData={hasSetupData} onActivate={onActivate} />
          <TwoFactorInfoBanner />
          <OptionCard options={twoFactorBenefits} />
          <SummaryCard title="Detalles importantes" data={twoFactorImportantDetails} />
        </div>

        <div className="flex w-full min-w-0 flex-col gap-6 xl:max-w-sm xl:shrink-0">
          <TwoFactorOperations />
          <TwoFactorRequirements />
          <TwoFactorRecommendedApps />
        </div>
      </div>

      <TwoFactorActivationForm />
    </div>
  );
}

interface TwoFactorTitleProps {
  hasSetupData: boolean;
  onActivate: () => void;
}

function TwoFactorTitle({ hasSetupData, onActivate }: TwoFactorTitleProps): JSX.Element {
  const { resolvedAppearance } = useAppearance();

  return (
    <div className="flex flex-col items-start gap-4 rounded-xl border bg-card p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-6">
      <div className="flex min-w-0 items-start gap-4">
        <div className={cn(iconColorVariants.violet.iconBgClass, "rounded-3xl p-2")}>
          <ShieldCheck className={cn("h-12 w-12", iconColorVariants.violet.iconFgClass)} />
        </div>

        <div className="flex flex-col gap-2">
          <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
            Autenticación de dos factores (2FA)
          </h1>

          <p className="text-sm text-muted-foreground">
            Añade una capa adicional de seguridad a tu cuenta. Con 2FA, además de tu contraseña, se
            te solicitará un código único para verificar tu identidad.
          </p>

          <Badge
            variant={resolvedAppearance === "light" ? "destructive" : null}
            className="inline-flex dark:bg-red-700 dark:text-white"
          >
            <ShieldAlert />
            Desactivado
          </Badge>
        </div>
      </div>

      <div className="w-full sm:w-auto">
        {hasSetupData ? (
          <Button onClick={onActivate} className="w-full cursor-pointer sm:w-auto">
            <ShieldCheck />
            Continuar configuración
          </Button>
        ) : (
          <Form
            {...enable.form()}
            onError={() => toast.error("No se pudo activar 2FA. Inténtalo de nuevo.")}
            onSuccess={onActivate}
            className="w-full cursor-pointer sm:w-auto"
          >
            {({ processing }) => (
              <Button
                type="submit"
                className="w-full cursor-pointer sm:w-auto"
                disabled={processing}
              >
                {processing ? "Activando..." : "Activar 2FA"}
              </Button>
            )}
          </Form>
        )}
      </div>
    </div>
  );
}

function TwoFactorInfoBanner() {
  return (
    <Alert className={alertVariants.info}>
      <Info />
      <AlertTitle>Este PIN se solicitará cada vez que inicies sesión.</AlertTitle>
      <AlertDescription>
        Puedes obtenerlo desde una aplicación compatible con TOTP en tu teléfono.
      </AlertDescription>
    </Alert>
  );
}

function TwoFactorActivationForm() {
  return (
    <div className="flex flex-col items-start gap-4 rounded-xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        <ShieldAlert className="h-4 w-4 text-muted-foreground" />
        <span className="text-muted-foreground">¿Tienes dudas?</span>

        <span className="font-medium text-blue-700 dark:text-blue-500">Consulta nuestros FAQ</span>
      </div>

      <div className="flex items-center gap-2">
        <LockKeyhole className="h-4 w-4 text-muted-foreground" />
        <span className="text-sm text-muted-foreground">Tu seguridad es nuestra prioridad.</span>
      </div>
    </div>
  );
}

function TwoFactorOperations() {
  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">Cómo funciona</h2>
      </CardHeader>
      <CardContent>
        <Timeline steps={twoFactorOperationSteps} />
      </CardContent>
    </Card>
  );
}

function TwoFactorRequirements() {
  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">Requesitos</h2>
      </CardHeader>
      <CardContent>
        <div className="space-y-3 text-sm">
          {twoFactorRequirements.map((requirement) => (
            <div key={requirement.key} className="flex items-center gap-2">
              <CircleCheck className="text-green-500" />
              <p>{requirement.description}</p>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

function TwoFactorRecommendedApps() {
  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">Aplicaciones recomendadas</h2>
      </CardHeader>
      <CardContent>
        <div className="space-y-5 text-sm font-medium">
          {twoFactorRecommendedApps.map((app) => {
            const Icon = app.icon;
            const IconDark = app.iconDark;

            return (
              <div key={app.key} className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-4">
                  {IconDark ? (
                    <>
                      <Icon className="h-6 w-6 dark:hidden" />
                      <IconDark className="hidden h-6 w-6 dark:block" />
                    </>
                  ) : (
                    <Icon className="h-6 w-6" />
                  )}
                  <span>{app.name}</span>
                </div>

                <SquareArrowOutUpRight className="h-4 w-4 text-muted-foreground" />
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}

export default TwoFactorDisabled;
