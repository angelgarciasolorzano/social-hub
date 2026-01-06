import { useState } from "react";

import { Form, router, usePage } from "@inertiajs/react";

import { ShieldBan, ShieldCheck } from "lucide-react";

import ConfirmedPasswordStatusController from "@/actions/Laravel/Fortify/Http/Controllers/ConfirmedPasswordStatusController";

import { disable, enable } from "@/routes/two-factor";

import { TwoFactorRecoveryCodes, TwoFactorSetupModal } from "@/components/twoFactor";
import { Button } from "@/components/ui/button";

import { useTwoFactorAuth } from "@/hooks/use-two-factor-auth";

import { SharedData } from "@/types";

import ConfirmPassword from "../components/ConfirmPassword";

export default function TwoFactor() {
  const { auth } = usePage<SharedData>().props;
  const { requiresConfirmation = false, twoFactorEnabled = false } = auth.user;

  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth();

  const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
  const [showConfirmationModal, setShowConfirmationModal] = useState<boolean>(false);

  const handleEnable2FA = async () => {
    const response = await fetch(ConfirmedPasswordStatusController.show.url(), {
      headers: { Accept: "application/json" },
    });

    const data = await response.json();

    if (data.confirmed) {
      router.post(
        enable.url(),
        {},
        {
          onSuccess: () => {
            setShowSetupModal(true);
          },
        },
      );
    } else {
      setShowConfirmationModal(true);
    }
  };

  return (
    <div className="space-y-6">
      {twoFactorEnabled ? (
        <div className="flex flex-col items-start justify-start space-y-4">
          <p className="text-muted-foreground">
            Con la autenticación de dos factores habilitada, se le solicitará un PIN aleatorio y
            seguro durante el inicio de sesión, que puede recuperar desde la aplicación compatible
            con TOTP en su teléfono.
          </p>

          <TwoFactorRecoveryCodes
            errors={errors}
            fetchRecoveryCodes={fetchRecoveryCodes}
            recoveryCodesList={recoveryCodesList}
          />

          <div className="relative inline">
            <Form {...disable.form()}>
              {({ processing }) => (
                <Button
                  type="submit"
                  className="cursor-pointer bg-destructive hover:bg-destructive/90 dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
                  disabled={processing}
                >
                  <ShieldBan /> Deshabilitar 2FA
                </Button>
              )}
            </Form>
          </div>
        </div>
      ) : (
        <div className="flex flex-col items-start justify-start space-y-4">
          <p className="text-muted-foreground">
            Al activar la autenticación de dos factores, se le solicitará un PIN seguro al iniciar
            sesión. Puede obtener este PIN desde una aplicación compatible con TOTP en su teléfono.
          </p>

          <div>
            {hasSetupData ? (
              <Button className="cursor-pointer" onClick={() => setShowSetupModal(true)}>
                <ShieldCheck />
                Continuar configuración
              </Button>
            ) : (
              <Button className="cursor-pointer" onClick={() => handleEnable2FA()}>
                <ShieldCheck />
                Enable 2FA
              </Button>
            )}
          </div>
        </div>
      )}

      {showConfirmationModal && (
        <ConfirmPassword
          onConfirmed={handleEnable2FA}
          open={showConfirmationModal}
          setOpen={setShowConfirmationModal}
        />
      )}

      <TwoFactorSetupModal
        onClose={() => setShowSetupModal(false)}
        clearSetupData={clearSetupData}
        errors={errors}
        fetchSetupData={fetchSetupData}
        isOpen={showSetupModal}
        manualSetupKey={manualSetupKey}
        qrCodeSvg={qrCodeSvg}
        requiresConfirmation={requiresConfirmation}
        twoFactorEnabled={twoFactorEnabled}
      />
    </div>
  );
}
