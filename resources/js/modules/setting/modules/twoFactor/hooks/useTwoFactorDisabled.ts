import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

interface UseTwoFactorDisabledReturn {
  showSetupModal: boolean;
  setShowSetupModal: Dispatch<SetStateAction<boolean>>;
}

export function useTwoFactorDisabled(): UseTwoFactorDisabledReturn {
  const [showSetupModal, setShowSetupModal] = useState<boolean>(false);

  return {
    showSetupModal,
    setShowSetupModal,
  };
}
