import type { LucideIcon } from "lucide-react";
import { BellIcon, HouseIcon, SearchIcon, UserRoundIcon, UsersIcon } from "lucide-react";

import { home } from "@/shared/wayfinder/routes";
import { index as friendshipsIndex, search } from "@/shared/wayfinder/routes/friendships";
import { index as notificationsIndex } from "@/shared/wayfinder/routes/notifications";
import { index as profileIndex } from "@/shared/wayfinder/routes/profile";

export type HomeSidebarBadge = "pendingFriendRequests" | "unreadNotifications";

export interface HomeSidebarNavigationItem {
  badge?: HomeSidebarBadge;
  href: string;
  icon: LucideIcon;
  labelKey: string;
}

export const homeSidebarNavigationItems: HomeSidebarNavigationItem[] = [
  { href: home.url(), icon: HouseIcon, labelKey: "home/sidebar.navigation.home" },
  { href: search.url(), icon: SearchIcon, labelKey: "home/sidebar.navigation.search" },
  {
    badge: "pendingFriendRequests",
    href: friendshipsIndex.url(),
    icon: UsersIcon,
    labelKey: "home/sidebar.navigation.friends",
  },
  {
    badge: "unreadNotifications",
    href: notificationsIndex.url(),
    icon: BellIcon,
    labelKey: "home/sidebar.navigation.notifications",
  },
  { href: profileIndex.url(), icon: UserRoundIcon, labelKey: "home/sidebar.navigation.profile" },
];
