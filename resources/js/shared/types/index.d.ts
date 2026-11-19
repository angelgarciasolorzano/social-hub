import type { Announcement } from "@/modules/announcement/types/announcement";
import type { FriendshipListItem } from "@/modules/friendship/types/friendshipList";

import type { Auth } from "./user";

export interface BreadcrumbItem {
  href: string;
  title: string;
}

export type Locale = "en" | "es";

export interface TranslationTree {
  [key: string]: string | TranslationTree;
}

export interface SharedFriendRequests {
  pendingCount: number;
  recent: FriendshipListItem[];
}

export interface SharedData {
  [key: string]: unknown;
  announcements: Announcement[];
  auth: Auth;
  friendRequests?: SharedFriendRequests | null;
  locale: Locale;
  name: string;
  quote: { message: string; author: string };
  sidebarOpen: boolean;
  translations: Record<string, TranslationTree>;
  unreadNotificationsCount?: number;
}

export type * from "./lengthAwarePagination";
export type * from "./user";
