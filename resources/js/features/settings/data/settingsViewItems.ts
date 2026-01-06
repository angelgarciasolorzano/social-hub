import { IconType } from "react-icons";
import { FaCircleUser, FaUserLock, FaUserShield } from "react-icons/fa6";
import { LuMonitorSmartphone } from "react-icons/lu";

import { SettingLabelSidebar } from "./settingsSidebarItems";

interface SettingsViewItems {
  label: string;
  description: string;
  icon: IconType;
}

export const settingsViewItems: Record<SettingLabelSidebar, SettingsViewItems> = {
  [SettingLabelSidebar.Perfil]: {
    label: "Información de perfil",
    description: "Configura tu información personal.",
    icon: FaCircleUser,
  },
  [SettingLabelSidebar.Contraseña]: {
    label: "Contraseña",
    description: "Cambia tu contraseña para mantener tu cuenta segura.",
    icon: FaUserLock,
  },
  [SettingLabelSidebar.TwoFactor]: {
    label: "Two Factor Authentication",
    description: "Añade una capa extra de seguridad a tu cuenta.",
    icon: FaUserShield,
  },
  [SettingLabelSidebar.Apariencia]: {
    label: "Apariencia",
    description: "Personaliza el tema y la apariencia de la aplicación.",
    icon: LuMonitorSmartphone,
  },
};
