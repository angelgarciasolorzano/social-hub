import { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdMonitor, MdOutlineSecurity } from "react-icons/md";

export type SettingLabelSidebar =
  | "Perfil"
  | "Contraseña"
  | "Two Factor Authentication"
  | "Apariencia";

interface SettingItemSidebar {
  label: SettingLabelSidebar;
  icon: IconType;
}

export const settingSidebarItems: SettingItemSidebar[] = [
  {
    label: "Perfil",
    icon: LuUserRoundCog,
  },
  {
    label: "Contraseña",
    icon: MdLockOutline,
  },
  {
    label: "Two Factor Authentication",
    icon: MdOutlineSecurity,
  },
  {
    label: "Apariencia",
    icon: MdMonitor,
  },
];
