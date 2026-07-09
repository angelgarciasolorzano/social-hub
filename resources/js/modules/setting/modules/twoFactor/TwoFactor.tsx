import { Head } from "@inertiajs/react";

import TwoFactorSetupDialog from "./components/dialog/twoFactorDisabled/TwoFactorSetupDialog";
import { useTwoFactorAuth } from "./hooks/useTwoFactorAuth";
import { useTwoFactorDisabled } from "./hooks/useTwoFactorDisabled";
import TwoFactorDisabled from "./views/TwoFactorDisabled";
import TwoFactorEnable from "./views/TwoFactorEnable";

interface Props {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
}

export default function TwoFactor({
  canManageTwoFactor = false,
  requiresConfirmation = false,
  twoFactorEnabled = false,
}: Props) {
  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    recoveryCodesList,
    clearSetupData,
    fetchSetupData,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth();

  const { showSetupModal, setShowSetupModal } = useTwoFactorDisabled();

  return (
    <>
      <Head title="Two Factor Authentication" />

      {canManageTwoFactor && (
        <>
          {twoFactorEnabled ? (
            <TwoFactorEnable />
          ) : (
            <TwoFactorDisabled
              hasSetupData={hasSetupData}
              onActivate={() => {
                setShowSetupModal(true);
              }}
            />
          )}

          <TwoFactorSetupDialog
            clearSetupData={clearSetupData}
            errors={errors}
            fetchRecoveryCodes={fetchRecoveryCodes}
            fetchSetupData={fetchSetupData}
            isOpen={showSetupModal}
            manualSetupKey={manualSetupKey}
            onClose={() => {
              setShowSetupModal(false);
            }}
            qrCodeSvg={qrCodeSvg}
            recoveryCodesList={recoveryCodesList}
            requiresConfirmation={requiresConfirmation}
            twoFactorEnabled={twoFactorEnabled}
          />
        </>
      )}
    </>
  );
}
