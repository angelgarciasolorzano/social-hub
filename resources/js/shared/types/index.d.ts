import type { Announcement } from "@/modules/announcement/types/announcement";

import type { Auth, User } from "./user";

export interface BreadcrumbItem {
  href: string;
  title: string;
}

export type Locale = "en" | "es";

export interface TranslationTree {
  [key: string]: string | TranslationTree;
}

export interface SharedData {
  [key: string]: unknown;
  announcements: Announcement[];
  auth: Auth;
  locale: Locale;
  name: string;
  quote: { message: string; author: string };
  search_results: User[] | null;
  sidebarOpen: boolean;
  translations: Record<string, TranslationTree>;
  unreadNotificationsCount?: number;
}

export type * from "./lengthAwarePagination";
export type * from "./user";
