import { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdMonitor, MdOutlineSecurity } from "react-icons/md";

export enum SettingLabelSidebar {
  Profile = "Perfil",
  Password = "Contraseña",
  TwoFactor = "Two Factor Authentication",
  Appearance = "Apariencia",
}

interface SettingItemSidebar {
  label: SettingLabelSidebar;
  icon: IconType;
}

export const settingSidebarItems: SettingItemSidebar[] = [
  {
    label: SettingLabelSidebar.Profile,
    icon: LuUserRoundCog,
  },
  {
    label: SettingLabelSidebar.Password,
    icon: MdLockOutline,
  },
  {
    label: SettingLabelSidebar.TwoFactor,
    icon: MdOutlineSecurity,
  },
  {
    label: SettingLabelSidebar.Appearance,
    icon: MdMonitor,
  },
];
