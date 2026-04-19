import { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdMonitor, MdOutlineSecurity } from "react-icons/md";

export enum SettingLabelSidebar {
  Appearance = "Apariencia",
  Password = "Contraseña",
  Profile = "Perfil",
  TwoFactor = "Two Factor Authentication",
}

interface SettingItemSidebar {
  icon: IconType;
  label: SettingLabelSidebar;
}

export const settingSidebarItems: SettingItemSidebar[] = [
  {
    icon: LuUserRoundCog,
    label: SettingLabelSidebar.Profile,
  },
  {
    icon: MdLockOutline,
    label: SettingLabelSidebar.Password,
  },
  {
    icon: MdOutlineSecurity,
    label: SettingLabelSidebar.TwoFactor,
  },
  {
    icon: MdMonitor,
    label: SettingLabelSidebar.Appearance,
  },
];
