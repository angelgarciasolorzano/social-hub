import type { IconType } from "react-icons";
import { SiFusionauth } from "react-icons/si";

import type { SettingLabelSidebarValue } from "./settingSidebarItems";
import { SettingLabelSidebar } from "./settingSidebarItems";

interface PasswordProtectedViewMeta {
  description: string;
  requiredFor?: SettingLabelSidebarValue;
  title: string;
  icon: IconType;
}

export const passwordProtectedViewMeta: Partial<
  Record<SettingLabelSidebarValue, PasswordProtectedViewMeta>
> = {
  [SettingLabelSidebar.TwoFactor]: {
    description: "Debes confirmar tu contraseña para gestionar la autenticación de dos factores.",
    requiredFor: SettingLabelSidebar.TwoFactor,
    title: "Two Factor Authentication",
    icon: SiFusionauth,
  },
};
