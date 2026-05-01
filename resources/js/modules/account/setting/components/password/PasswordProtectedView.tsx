import type { PropsWithChildren } from "react";

import type { IconType } from "react-icons";

import { Button } from "@/shared/components/ui/button";

import type { SettingLabelSidebarValue } from "../../data/settingSidebarItems";
import { usePasswordConfirmation } from "../../hooks/usePasswordConfirmation";
import ConfirmPassword from "./ConfirmPassword";

interface PasswordProtectedViewProps extends PropsWithChildren {
  active: SettingLabelSidebarValue;
  description?: string;
  requiredFor?: SettingLabelSidebarValue;
  title?: string;
  icon?: IconType;
}

function PasswordProtectedView({
  active,
  children,
  description,
  requiredFor,
  title,
  icon: Icon,
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
        <div className="flex flex-col gap-2 justify-center items-center">
          {Icon && <Icon className="w-12 h-12" />}

          <h2 className="font-semibold">{title ?? "Debes confirmar tu contraseña"}</h2>
        </div>

        <p>{description ?? "Debes confirmar tu contraseña para continuar."}</p>

        <Button className="cursor-pointer" onClick={() => openConfirmPasswordModal()}>
          Confirmar Contraseña
        </Button>
      </main>
    );
  }

  return <>{children}</>;
}

export default PasswordProtectedView;
