import type { IconType } from "react-icons";
import { LuBell, LuHouse, LuSearch, LuUserRound, LuUsers } from "react-icons/lu";

import { home } from "@/shared/wayfinder/routes";
import { index as friendshipsIndex, search } from "@/shared/wayfinder/routes/friendships";
import { index as notificationsIndex } from "@/shared/wayfinder/routes/notifications";
import { index as profileIndex } from "@/shared/wayfinder/routes/profile";

export type HomeSidebarBadge = "pendingFriendRequests" | "unreadNotifications";

export interface HomeSidebarNavigationItem {
  badge?: HomeSidebarBadge;
  href: string;
  icon: IconType;
  labelKey: string;
}

export const homeSidebarNavigationItems: HomeSidebarNavigationItem[] = [
  { href: home.url(), icon: LuHouse, labelKey: "home/sidebar.navigation.home" },
  { href: search.url(), icon: LuSearch, labelKey: "home/sidebar.navigation.search" },
  {
    badge: "pendingFriendRequests",
    href: friendshipsIndex.url(),
    icon: LuUsers,
    labelKey: "home/sidebar.navigation.friends",
  },
  {
    badge: "unreadNotifications",
    href: notificationsIndex.url(),
    icon: LuBell,
    labelKey: "home/sidebar.navigation.notifications",
  },
  { href: profileIndex.url(), icon: LuUserRound, labelKey: "home/sidebar.navigation.profile" },
];
