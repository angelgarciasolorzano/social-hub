import { useEffect, useRef, useState } from "react";

import { Head } from "@inertiajs/react";

import { useTwoFactorAuth } from "@/shared/hooks";

import TwoFactorAuthenticationDisabled from "./views/TwoFactorAuthenticationDisabled";
import TwoFactorAuthenticationEnable from "./views/TwoFactorAuthenticationEnable";

interface Props {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
}

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
            <TwoFactorAuthenticationEnable />
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
