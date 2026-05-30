import type { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import {
  CircleCheck,
  Info,
  LockKeyhole,
  ShieldAlert,
  ShieldCheck,
  SquareArrowOutUpRight,
} from "lucide-react";

import { enable } from "@/shared/wayfinder/routes/two-factor";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/ui/alert";
import { Badge } from "@/shared/components/ui/badge";
import { Button } from "@/shared/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/ui/card";

import { OptionCard } from "../components/OptionCard";
import SummaryCard from "../components/SummaryCard";
import Timeline from "../components/Timeline";
import TwoFactorSetupModal from "../components/TwoFactorSetupModal";
import {
  twoFactorBenefits,
  twoFactorImportantDetails,
  twoFactorOperationSteps,
  twoFactorRecommendedApps,
  twoFactorRequirements,
} from "../data/twoFactorDisabled";

interface TwoFactorAuthenticationDisabledProps {
  hasSetupData: boolean;
  setShowSetupModal: Dispatch<SetStateAction<boolean>>;
  clearSetupData: () => void;
  errors: string[];
  fetchSetupData: () => Promise<void>;
  isOpen: boolean;
  manualSetupKey: string | null;
  onClose: () => void;
  qrCodeSvg: string | null;
  requiresConfirmation: boolean;
  twoFactorEnabled: boolean;
}

function TwoFactorAuthenticationDisabled(props: TwoFactorAuthenticationDisabledProps) {
  const {
    hasSetupData,
    setShowSetupModal,
    clearSetupData,
    errors,
    fetchSetupData,
    isOpen,
    manualSetupKey,
    onClose,
    qrCodeSvg,
    requiresConfirmation,
    twoFactorEnabled,
  } = props;
  return (
    <div className="flex flex-col gap-6">
      <div className="flex gap-6">
        <div className="flex flex-col gap-8">
          <TwoFactorTitle setShowSetupModal={setShowSetupModal} hasSetupData={hasSetupData} />
          <TwoFactorInfoBanner />
          <OptionCard options={twoFactorBenefits} />
          <SummaryCard title="Detalles importantes" data={twoFactorImportantDetails} />
        </div>

        <div className="flex flex-col gap-6">
          <TwoFactorOperations />
          <TwoFactorRequirements />
          <TwoFactorRecommendedApps />
        </div>
      </div>

      <TwoFactorActivationForm />

      <TwoFactorSetupModal
        onClose={onClose}
        clearSetupData={clearSetupData}
        errors={errors}
        fetchSetupData={fetchSetupData}
        isOpen={isOpen}
        manualSetupKey={manualSetupKey}
        qrCodeSvg={qrCodeSvg}
        requiresConfirmation={requiresConfirmation}
        twoFactorEnabled={twoFactorEnabled}
      />
    </div>
  );
}

type TwoFactorTitleProps = Pick<
  TwoFactorAuthenticationDisabledProps,
  "setShowSetupModal" | "hasSetupData"
>;

function TwoFactorTitle({ setShowSetupModal, hasSetupData }: TwoFactorTitleProps) {
  return (
    <div className="flex items-center gap-24 rounded-md border border-violet-200 bg-violet-300/5 p-8 shadow-sm dark:border-violet-500/20 dark:bg-violet-900/5">
      <div className="flex items-start gap-4">
        <div className="rounded-3xl bg-violet-100/50 p-2 dark:bg-violet-900/20">
          <ShieldCheck className="h-12 w-12 text-violet-700 dark:text-violet-500" />
        </div>

        <div className="flex flex-col gap-2">
          <h2 className="text-xl font-semibold">Autenticación de dos factores (2FA)</h2>

          <p className="text-sm text-muted-foreground">
            Añade una capa adicional de seguridad a tu cuenta. Con 2FA, además de tu contraseña, se
            te solicitará un código único para verificar tu identidad.
          </p>

          <Badge variant="destructive" className="inline-flex">
            <ShieldAlert />
            Desactivado
          </Badge>
        </div>
      </div>

      <div>
        {hasSetupData ? (
          <Button onClick={() => setShowSetupModal(true)} className="cursor-pointer">
            <ShieldCheck />
            Continuar configuración
          </Button>
        ) : (
          <Form
            {...enable.form()}
            onSuccess={() => setShowSetupModal(true)}
            className="cursor-pointer"
          >
            {({ processing }) => (
              <Button type="submit" className="cursor-pointer" disabled={processing}>
                Activar 2FA
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
    <Alert className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-500">
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
    <div className="flex items-center justify-between rounded-md border p-6 shadow-sm">
      <div className="flex items-center gap-2 text-sm">
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
        <CardTitle>Cómo funciona</CardTitle>
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
        <CardTitle>Requesitos</CardTitle>
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
        <CardTitle>Aplicaciones recomendadas</CardTitle>
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

export default TwoFactorAuthenticationDisabled;
