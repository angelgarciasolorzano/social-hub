import { useEffect, useState } from "react";

import ConfirmedPasswordStatusController from "@/actions/Laravel/Fortify/Http/Controllers/ConfirmedPasswordStatusController";

import { SettingLabelSidebar } from "../data/settingsSidebarItems";

export const usePasswordConfirmation = (active: SettingLabelSidebar) => {
  const [isPasswordConfirmed, setIsPasswordConfirmed] = useState<boolean>(false);
  const [isConfirmPasswordModal, setIsConfirmPasswordModal] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(false);

  const checkPasswordConfirmation = async (): Promise<boolean> => {
    try {
      const response = await fetch(ConfirmedPasswordStatusController.show.url(), {
        headers: { Accept: "application/json" },
      }).then((res) => res.json());

      return response.confirmed as boolean;
    } catch {
      return false;
    }
  };

  const passwordConfirmed = (confirmed: boolean) => {
    setIsPasswordConfirmed(confirmed);
  };

  const openConfirmPasswordModal = () => setIsConfirmPasswordModal(true);

  useEffect(() => {
    if (active === SettingLabelSidebar.TwoFactor) {
      setLoading(true);
      checkPasswordConfirmation()
        .then((data) => {
          setIsPasswordConfirmed(data);
          setIsConfirmPasswordModal(!data);
        })
        .finally(() => setLoading(false));
    }
  }, [active]);

  return {
    isPasswordConfirmed,
    isConfirmPasswordModal,
    loading,
    passwordConfirmed,
    openConfirmPasswordModal,
    setIsConfirmPasswordModal,
  };
};
