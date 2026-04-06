import type { IconType } from "react-icons";
import { FaRegUser } from "react-icons/fa6";
import { FiHome } from "react-icons/fi";
import { MdOutlineLogout } from "react-icons/md";
import { PiNutBold } from "react-icons/pi";

import type { RouteDefinition } from "@/shared/wayfinder/wayfinder";

import { dashboard, logout } from "@/shared/wayfinder/routes";
import { index } from "@/shared/wayfinder/routes/profile";

interface MenuItem {
  text: string;
  url?: string | RouteDefinition<"get" | "post">;
  method?: "post" | "get";
  action?: "openConfigModal";
  icon: IconType;
}

export const menuItems: MenuItem[] = [
  {
    text: "Inicio",
    url: dashboard.url(),
    icon: FiHome,
  },
  {
    text: "Perfil",
    url: index.url(),
    icon: FaRegUser,
  },
  {
    text: "Configuración",
    action: "openConfigModal",
    icon: PiNutBold,
  },
  {
    text: "Cerrar Sesión",
    url: logout(),
    method: "post",
    icon: MdOutlineLogout,
  },
];
