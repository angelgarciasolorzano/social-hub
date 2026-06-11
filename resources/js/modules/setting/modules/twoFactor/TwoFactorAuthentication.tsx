import { Head } from "@inertiajs/react";

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
