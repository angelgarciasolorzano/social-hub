import { Head } from "@inertiajs/react";

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
  return (
    <>
      <Head title="Two Factor Authentication" />

      {canManageTwoFactor && (
        <>
          {twoFactorEnabled ? (
            <TwoFactorEnable />
          ) : (
            <>
              <TwoFactorDisabled
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
