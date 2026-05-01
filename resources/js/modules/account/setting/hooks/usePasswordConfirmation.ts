import { useEffect, useState } from "react";

import { show } from "@/shared/wayfinder/actions/Laravel/Fortify/Http/Controllers/ConfirmedPasswordStatusController";

import type { SettingLabelSidebarValue } from "../data/settingSidebarItems";
import { SettingLabelSidebar } from "../data/settingSidebarItems";

export const usePasswordConfirmation = (active: SettingLabelSidebarValue) => {
  const [isPasswordConfirmed, setIsPasswordConfirmed] = useState<boolean>(false);
  const [isConfirmPasswordModal, setIsConfirmPasswordModal] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(false);

  const checkPasswordConfirmation = async (): Promise<boolean> => {
    try {
      const response = await fetch(show.url(), {
        headers: { Accept: "application/json" },
      }).then((res) => res.json());

      return response.confirmed as boolean;
    } catch {
      return false;
    }
  };

  const passwordConfirmed = (confirmed: boolean): void => {
    setIsPasswordConfirmed(confirmed);
  };

  const openConfirmPasswordModal = (): void => setIsConfirmPasswordModal(true);

  useEffect(() => {
    if (active === SettingLabelSidebar.TwoFactor) {
      const passwordConfirmation = async (): Promise<void> => {
        setLoading(true);

        try {
          const data = await checkPasswordConfirmation();
          setIsPasswordConfirmed(data);
          setIsConfirmPasswordModal(!data);
        } finally {
          setLoading(false);
        }
      };

      passwordConfirmation();
    }
  }, [active]);

  return {
    isConfirmPasswordModal,
    isPasswordConfirmed,
    loading,
    openConfirmPasswordModal,
    passwordConfirmed,
    setIsConfirmPasswordModal,
  };
};
