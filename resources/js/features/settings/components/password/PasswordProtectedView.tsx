import { PropsWithChildren } from "react";

import { Button } from "@/components/ui/button";

import { SettingLabelSidebar } from "../../data/settingsSidebarItems";
import { usePasswordConfirmation } from "../../hooks/usePasswordConfirmation";
import ConfirmPassword from "./ConfirmPassword";

interface PasswordProtectedViewProps extends PropsWithChildren {
  active: SettingLabelSidebar;
  description: string;
  requiredFor: SettingLabelSidebar;
  title: string;
}

function PasswordProtectedView({
  active,
  children,
  description,
  requiredFor,
  title,
}: PasswordProtectedViewProps) {
  const {
    isConfirmPasswordModal,
    isPasswordConfirmed,
    loading,
    openConfirmPasswordModal,
    passwordConfirmed,
    setIsConfirmPasswordModal,
  } = usePasswordConfirmation(active);

  if (active === requiredFor && !isPasswordConfirmed) {
    if (isConfirmPasswordModal) {
      return (
        <ConfirmPassword
          onConfirmed={() => passwordConfirmed(true)}
          open={isConfirmPasswordModal}
          setOpen={setIsConfirmPasswordModal}
        />
      );
    }

    if (loading) return null;

    return (
      <main className="flex flex-1 flex-col items-center justify-center gap-4 px-20 text-center">
        <h2 className="font-semibold">{title}</h2>

        <p>{description}</p>

        <Button className="cursor-pointer" onClick={() => openConfirmPasswordModal()}>
          Confirmar Contraseña
        </Button>
      </main>
    );
  }

  return <>{children}</>;
}

export default PasswordProtectedView;
