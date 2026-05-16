import type { IconType } from "react-icons";
import { FaRegUser } from "react-icons/fa6";
import { FiHome } from "react-icons/fi";
import { MdOutlineLogout } from "react-icons/md";
import { PiNutBold } from "react-icons/pi";

import type { RouteDefinition } from "@/shared/wayfinder/wayfinder";

import { edit } from "@/shared/wayfinder/actions/App/User/Controllers/ProfileController";

import { home, logout } from "@/shared/wayfinder/routes";
import { index } from "@/shared/wayfinder/routes/profile";

export const UrlMethod = {
  POST: "post",
  GET: "get",
} as const;

export const ActionItem = {
  OpenConfigModal: "openConfigModal",
} as const;

export type UrlsMethodType = (typeof UrlMethod)[keyof typeof UrlMethod];

export type ActionItemType = (typeof ActionItem)[keyof typeof ActionItem];

interface MenuItem {
  text: string;
  url?: string | RouteDefinition<"get" | "post">;
  method?: UrlsMethodType;
  action?: ActionItemType;
  icon: IconType;
}

export const homeHeaderProfileMenuItems: MenuItem[] = [
  {
    text: "Inicio",
    url: home.url(),
    icon: FiHome,
  },
  {
    text: "Perfil",
    url: index.url(),
    icon: FaRegUser,
  },
  {
    text: "Configuración",
    url: edit.url(),
    icon: PiNutBold,
  },
  {
    text: "Cerrar Sesión",
    url: logout(),
    method: UrlMethod.POST,
    icon: MdOutlineLogout,
  },
];
