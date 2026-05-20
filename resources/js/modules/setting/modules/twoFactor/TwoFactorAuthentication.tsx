import { useEffect, useRef, useState } from "react";

import { Form, Head } from "@inertiajs/react";

import { ShieldCheck } from "lucide-react";

import { disable, enable } from "@/shared/wayfinder/routes/two-factor";

import { Button } from "@/shared/components/ui/button";

import { useTwoFactorAuth } from "@/shared/hooks";

import TwoFactorRecoveryCodes from "./components/TwoFactorRecoveryCodes";
import TwoFactorSetupModal from "./components/TwoFactorSetupModal";

type Props = {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
};

export default function Security({
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
      <Head title="Security settings" />

      <h1 className="sr-only">Security settings</h1>

      {canManageTwoFactor && (
        <div className="space-y-6">
          {twoFactorEnabled ? (
            <div className="flex flex-col items-start justify-start space-y-4">
              <p className="text-sm text-muted-foreground">
                You will be prompted for a secure, random pin during login, which you can retrieve
                from the TOTP-supported application on your phone.
              </p>

              <div className="relative inline">
                <Form {...disable.form()}>
                  {({ processing }) => (
                    <Button type="submit" disabled={processing} variant="destructive">
                      Disable 2FA
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
            <div className="flex flex-col items-start justify-start space-y-4">
              <p className="text-sm text-muted-foreground">
                When you enable two-factor authentication, you will be prompted for a secure pin
                during login. This pin can be retrieved from a TOTP-supported application on your
                phone.
              </p>

              <div>
                {hasSetupData ? (
                  <Button onClick={() => setShowSetupModal(true)}>
                    <ShieldCheck />
                    Continue setup
                  </Button>
                ) : (
                  <Form {...enable.form()} onSuccess={() => setShowSetupModal(true)}>
                    {({ processing }) => (
                      <Button type="submit" disabled={processing}>
                        Enable 2FA
                      </Button>
                    )}
                  </Form>
                )}
              </div>
            </div>
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
      )}
    </>
  );
}
