import type { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdMonitor, MdOutlineSecurity } from "react-icons/md";

export const SettingLabelSidebar = {
  Appearance: "Apariencia",
  Password: "Contraseña",
  Profile: "Perfil",
  TwoFactor: "Two Factor Authentication",
} as const;

export type SettingLabelSidebarValue =
  (typeof SettingLabelSidebar)[keyof typeof SettingLabelSidebar];

interface SettingItemSidebar {
  icon: IconType;
  label: SettingLabelSidebarValue;
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
