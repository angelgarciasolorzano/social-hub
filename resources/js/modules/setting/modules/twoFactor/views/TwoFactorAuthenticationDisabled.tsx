import type { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import {
  CircleCheck,
  Clock,
  Info,
  LockKeyhole,
  ShieldCheck,
  SquareArrowOutUpRight,
} from "lucide-react";

import { enable } from "@/shared/wayfinder/routes/two-factor";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/ui/alert";
import { Button } from "@/shared/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/shared/components/ui/card";

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
          <TwoFactorTitle />
          <TwoFactorInfoBanner />
          <TwoFactorBenefit />
          <TwoFactorImportantDetails />
        </div>

        <div className="flex flex-col gap-6">
          <TwoFactorOperations />
          <TwoFactorRequirements />
          <TwoFactorRecommendedApps />
        </div>
      </div>

      <TwoFactorActivationForm hasSetupData={hasSetupData} setShowSetupModal={setShowSetupModal} />

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

function TwoFactorTitle() {
  return (
    <div className="flex items-center gap-4">
      <div className="rounded-3xl bg-green-100/50 p-2 dark:bg-green-900/20">
        <ShieldCheck className="h-12 w-12 text-green-700 dark:text-green-500" />
      </div>

      <div className="flex flex-col gap-2">
        <h2 className="text-xl font-semibold">Autenticación de dos factores (2FA)</h2>

        <p className="text-sm text-muted-foreground">
          Añade una capa adicional de seguridad a tu cuenta. Con 2FA, además de tu contraseña, se te
          solicitará un código único para verificar tu identidad.
        </p>
      </div>
    </div>
  );
}

function TwoFactorInfoBanner() {
  return (
    <Alert className="border-blue-100 bg-blue-300/10 dark:border-blue-500/30 dark:bg-blue-500/10">
      <Info />
      <AlertTitle>Este PIN se solicitará cada vez que inicies sesión.</AlertTitle>
      <AlertDescription>
        Puedes obtenerlo desde una aplicación compatible con TOTP en tu teléfono.
      </AlertDescription>
    </Alert>
  );
}

function TwoFactorBenefit() {
  return (
    <div className="flex flex-col gap-5">
      <h3 className="text-lg font-semibold">Beneficios de activar 2FA</h3>

      <div className="flex items-stretch gap-4">
        {twoFactorBenefits.map((benefit) => {
          const Icon = benefit.icon;

          return (
            <div key={benefit.key} className="flex flex-1 gap-4 rounded-md border p-4 shadow-sm">
              <div className={`flex h-12 w-12 shrink-0 rounded-md p-2 ${benefit.iconBgColor}`}>
                <Icon className={`h-8 w-8 ${benefit.iconColor}`} />
              </div>

              <div className="space-y-0.5">
                <h4 className="text-sm font-medium">{benefit.title}</h4>

                <p className="text-sm text-muted-foreground">{benefit.description}</p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function TwoFactorImportantDetails() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Detalles importantes</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-4">
          {twoFactorImportantDetails.map((detail) => {
            const Icon = detail.icon;

            return (
              <div key={detail.key} className="flex gap-4 p-2">
                <div className={`flex h-12 w-12 rounded-md p-2 ${detail.iconBgColor}`}>
                  <Icon className={`h-8 w-8 ${detail.iconColor}`} />
                </div>

                <div className="space-y-1">
                  <h4 className="text-sm font-medium">{detail.title}</h4>

                  <p className="text-sm text-muted-foreground">{detail.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}

type TwoFactorActivationFormProps = Pick<
  TwoFactorAuthenticationDisabledProps,
  "hasSetupData" | "setShowSetupModal"
>;

function TwoFactorActivationForm({
  hasSetupData,
  setShowSetupModal,
}: TwoFactorActivationFormProps) {
  return (
    <div className="flex items-center justify-between rounded-md border p-6 shadow-sm">
      <div className="flex items-center gap-6">
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

        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm text-muted-foreground">Te tomara menos de 2 minutos</span>
        </div>
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
