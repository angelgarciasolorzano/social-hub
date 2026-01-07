import { IconType } from "react-icons";
import { FaCircleUser, FaUserLock, FaUserShield } from "react-icons/fa6";
import { LuMonitorSmartphone } from "react-icons/lu";

import { SettingLabelSidebar } from "./settingsSidebarItems";

interface SettingsViewItems {
  description: string;
  headerAddon?: boolean;
  icon: IconType;
  label: string;
}

export const settingsViewItems: Record<SettingLabelSidebar, SettingsViewItems> = {
  [SettingLabelSidebar.Appearance]: {
    description: "Personaliza el tema y la apariencia de la aplicación.",
    icon: LuMonitorSmartphone,
    label: "Apariencia",
  },
  [SettingLabelSidebar.Password]: {
    description: "Cambia tu contraseña para mantener tu cuenta segura.",
    icon: FaUserLock,
    label: "Contraseña",
  },
  [SettingLabelSidebar.Profile]: {
    description: "Configura tu información personal.",
    headerAddon: true,
    icon: FaCircleUser,
    label: "Información de perfil",
  },
  [SettingLabelSidebar.TwoFactor]: {
    description: "Añade una capa extra de seguridad a tu cuenta.",
    headerAddon: true,
    icon: FaUserShield,
    label: "Two Factor Authentication",
  },
};
