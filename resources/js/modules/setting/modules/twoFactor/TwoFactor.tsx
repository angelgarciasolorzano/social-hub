import type { JSX } from "react";

import { Head } from "@inertiajs/react";

import { useDialog } from "@/shared/hooks/useDialog";

import TwoFactorSetupDialog from "./components/dialog/twoFactorDisabled/TwoFactorSetupDialog";
import { useTwoFactorAuth } from "./hooks/useTwoFactorAuth";
import type { TrustedDevice } from "./types/trustedDevice";
import TwoFactorDisabled from "./views/TwoFactorDisabled";
import TwoFactorEnable from "./views/TwoFactorEnable";

interface Props {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
  trustedDevices?: TrustedDevice[];
  trustedDevicesCount?: number;
  trustedDevicesForRevoke?: TrustedDevice[];
}

export default function TwoFactor({
  canManageTwoFactor = false,
  requiresConfirmation = false,
  twoFactorEnabled = false,
  trustedDevices = [],
  trustedDevicesCount = 0,
  trustedDevicesForRevoke = [],
}: Props): JSX.Element {
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

  const { open: showSetupDialog, setOpen: setShowSetupDialog } = useDialog();

  return (
    <>
      <Head title="Two Factor Authentication" />

      {canManageTwoFactor && (
        <>
          {twoFactorEnabled ? (
            <TwoFactorEnable
              trustedDevices={trustedDevices}
              trustedDevicesCount={trustedDevicesCount}
              trustedDevicesForRevoke={trustedDevicesForRevoke}
            />
          ) : (
            <TwoFactorDisabled
              hasSetupData={hasSetupData}
              onActivate={() => {
                setShowSetupDialog(true);
              }}
            />
          )}

          <TwoFactorSetupDialog
            clearSetupData={clearSetupData}
            errors={errors}
            fetchRecoveryCodes={fetchRecoveryCodes}
            fetchSetupData={fetchSetupData}
            isOpen={showSetupDialog}
            manualSetupKey={manualSetupKey}
            onClose={() => {
              setShowSetupDialog(false);
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
