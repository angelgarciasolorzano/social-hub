import type { IconType } from "react-icons";
import { GrShieldSecurity } from "react-icons/gr";
import { LuCircleUserRound, LuMonitorSmartphone } from "react-icons/lu";
import { MdOutlineLockPerson } from "react-icons/md";

import type { SettingLabelSidebarValue } from "./settingSidebarItems";
import { SettingLabelSidebar } from "./settingSidebarItems";

interface SettingHeaderItems {
  description: string;
  headerAddon?: boolean;
  icon: IconType;
  label: string;
}

export const settingHeaderItems: Record<SettingLabelSidebarValue, SettingHeaderItems> = {
  [SettingLabelSidebar.Appearance]: {
    description: "Personaliza el tema y la apariencia de la aplicación.",
    icon: LuMonitorSmartphone,
    label: "Apariencia",
  },
  [SettingLabelSidebar.Password]: {
    description: "Cambia tu contraseña para mantener tu cuenta segura.",
    icon: MdOutlineLockPerson,
    label: "Contraseña",
  },
  [SettingLabelSidebar.Profile]: {
    description: "Configura tu información personal.",
    headerAddon: true,
    icon: LuCircleUserRound,
    label: "Información de perfil",
  },
  [SettingLabelSidebar.TwoFactor]: {
    description: "Añade una capa extra de seguridad a tu cuenta.",
    headerAddon: true,
    icon: GrShieldSecurity,
    label: "Two Factor Authentication",
  },
};
