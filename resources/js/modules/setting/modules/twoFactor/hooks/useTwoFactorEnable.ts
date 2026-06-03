import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

interface UseTwoFactorEnableReturn {
  showRegenerateCodesDialog: boolean;
  setShowRegenerateCodesDialog: Dispatch<SetStateAction<boolean>>;
}

export function useTwoFactorEnable(): UseTwoFactorEnableReturn {
  const [showRegenerateCodesDialog, setShowRegenerateCodesDialog] = useState<boolean>(false);

  return {
    showRegenerateCodesDialog,
    setShowRegenerateCodesDialog,
  };
}
