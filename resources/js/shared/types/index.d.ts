import type { Announcement } from "@/modules/announcement/types/announcement";

import type { Auth, User } from "./user";

export interface BreadcrumbItem {
  href: string;
  title: string;
}

export interface SharedData {
  [key: string]: unknown;
  announcements: Announcement[];
  auth: Auth;
  name: string;
  quote: { message: string; author: string };
  search_results: User[] | null;
  sidebarOpen: boolean;
}

export type * from "./lengthAwarePagination";
export type * from "./user";
