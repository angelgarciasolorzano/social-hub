import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

interface UseTwoFactorEnableReturn {
  showRegenerateCodesDialog: boolean;
  showDisabledTwoFactorDialog: boolean;
  setShowDisabledTwoFactorDialog: Dispatch<SetStateAction<boolean>>;
  setShowRegenerateCodesDialog: Dispatch<SetStateAction<boolean>>;
}

export function useTwoFactorEnable(): UseTwoFactorEnableReturn {
  const [showRegenerateCodesDialog, setShowRegenerateCodesDialog] = useState<boolean>(false);
  const [showDisabledTwoFactorDialog, setShowDisabledTwoFactorDialog] = useState<boolean>(false);

  return {
    showRegenerateCodesDialog,
    setShowRegenerateCodesDialog,
    showDisabledTwoFactorDialog,
    setShowDisabledTwoFactorDialog,
  };
}
