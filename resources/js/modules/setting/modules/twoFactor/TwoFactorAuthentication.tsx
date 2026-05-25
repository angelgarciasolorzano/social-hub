import { useEffect, useRef, useState } from "react";

import { Form, Head } from "@inertiajs/react";

import { disable } from "@/shared/wayfinder/routes/two-factor";

import { Button } from "@/shared/components/ui/button";

import { useTwoFactorAuth } from "@/shared/hooks";

import TwoFactorRecoveryCodes from "./components/TwoFactorRecoveryCodes";
import TwoFactorAuthenticationDisabled from "./views/TwoFactorAuthenticationDisabled";

type Props = {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
};

export default function TwoFactorAuthentication({
  canManageTwoFactor = false,
  requiresConfirmation = false,
  twoFactorEnabled = false,
}: Props) {
  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    clearSetupData,
    clearTwoFactorAuthData,
    fetchSetupData,
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth();

  const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
  const prevTwoFactorEnabled = useRef(twoFactorEnabled);

  useEffect(() => {
    if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
      clearTwoFactorAuthData();
    }

    prevTwoFactorEnabled.current = twoFactorEnabled;
  }, [twoFactorEnabled, clearTwoFactorAuthData]);

  return (
    <>
      <Head title="Two Factor Authentication" />

      {canManageTwoFactor && (
        <>
          {twoFactorEnabled ? (
            <div className="flex flex-col items-start justify-start space-y-4">
              <p className="text-sm text-muted-foreground">
                Se te pedirá que ingreses un PIN seguro y aleatorio durante el inicio de sesión, el
                cual podrás recuperar desde la aplicación compatible con TOTP en tu teléfono.
              </p>

              <div className="relative inline">
                <Form {...disable.form()}>
                  {({ processing }) => (
                    <Button
                      type="submit"
                      className="cursor-pointer"
                      disabled={processing}
                      variant="destructive"
                    >
                      Desactivar 2FA
                    </Button>
                  )}
                </Form>
              </div>

              <TwoFactorRecoveryCodes
                errors={errors}
                fetchRecoveryCodes={fetchRecoveryCodes}
                recoveryCodesList={recoveryCodesList}
              />
            </div>
          ) : (
            <>
              <TwoFactorAuthenticationDisabled
                hasSetupData={hasSetupData}
                setShowSetupModal={setShowSetupModal}
                clearSetupData={clearSetupData}
                errors={errors}
                fetchSetupData={fetchSetupData}
                isOpen={showSetupModal}
                manualSetupKey={manualSetupKey}
                onClose={() => setShowSetupModal(false)}
                qrCodeSvg={qrCodeSvg}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
              />
            </>
          )}
        </>
      )}
    </>
  );
}
