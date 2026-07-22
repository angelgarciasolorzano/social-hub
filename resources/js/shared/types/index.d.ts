import type { Auth, User } from "./user";

export interface BreadcrumbItem {
  href: string;
  title: string;
}

export interface SharedData {
  [key: string]: unknown;
  auth: Auth;
  name: string;
  quote: { message: string; author: string };
  search_results: User[] | null;
  sidebarOpen: boolean;
}

export type * from "./pagination";
export type * from "./user";
