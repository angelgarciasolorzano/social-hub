import type { IconType } from "react-icons";
import { FaRegUser } from "react-icons/fa6";
import { FiHome } from "react-icons/fi";
import { MdOutlineLogout } from "react-icons/md";
import { PiNutBold } from "react-icons/pi";

import type { RouteDefinition } from "@/shared/wayfinder/wayfinder";

import { edit } from "@/shared/wayfinder/actions/App/User/Controllers/ProfileController";

import { home, logout } from "@/shared/wayfinder/routes";
import { index } from "@/shared/wayfinder/routes/profile";

const LabelProfile = {
  HOME: "Inicio",
  MY_PROFILE: "Mi Perfil",
  SETTING: "Configuración",
  LOGOUT: "Cerrar Sesión",
} as const;

export type LabelProfileType = (typeof LabelProfile)[keyof typeof LabelProfile];

type LabelProfileTypeKey = keyof typeof LabelProfile;

export const UrlMethod = {
  POST: "post",
  GET: "get",
} as const;

export type UrlsMethodType = (typeof UrlMethod)[keyof typeof UrlMethod];

interface MenuItem {
  key: LabelProfileTypeKey;
  label: LabelProfileType;
  url: string | RouteDefinition<"get" | "post">;
  method?: UrlsMethodType;
  icon: IconType;
}

export const homeHeaderProfileMenuItems: MenuItem[] = [
  {
    key: "HOME",
    label: LabelProfile.HOME,
    url: home.url(),
    icon: FiHome,
  },
  {
    key: "MY_PROFILE",
    label: LabelProfile.MY_PROFILE,
    url: index.url(),
    icon: FaRegUser,
  },
  {
    key: "SETTING",
    label: LabelProfile.SETTING,
    url: edit.url(),
    icon: PiNutBold,
  },
  {
    key: "LOGOUT",
    label: LabelProfile.LOGOUT,
    url: logout(),
    method: UrlMethod.POST,
    icon: MdOutlineLogout,
  },
];
