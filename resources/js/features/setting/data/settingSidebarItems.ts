import { IconType } from "react-icons";
import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdOutlineRoomPreferences, MdOutlineSecurity } from "react-icons/md";

interface SettingSidebarItem {
  label: string;
  icon: IconType;
}

export const settingSidebarItems: SettingSidebarItem[] = [
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
    label: "Preferencias",
    icon: MdOutlineRoomPreferences,
  },
];
