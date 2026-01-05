import { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdMonitor, MdOutlineSecurity } from "react-icons/md";

export enum SettingLabelSidebar {
  Perfil = "Perfil",
  Contraseña = "Contraseña",
  TwoFactor = "Two Factor Authentication",
  Apariencia = "Apariencia",
}

interface SettingItemSidebar {
  label: SettingLabelSidebar;
  icon: IconType;
}

export const settingSidebarItems: SettingItemSidebar[] = [
  {
    label: SettingLabelSidebar.Perfil,
    icon: LuUserRoundCog,
  },
  {
    label: SettingLabelSidebar.Contraseña,
    icon: MdLockOutline,
  },
  {
    label: SettingLabelSidebar.TwoFactor,
    icon: MdOutlineSecurity,
  },
  {
    label: SettingLabelSidebar.Apariencia,
    icon: MdMonitor,
  },
];
