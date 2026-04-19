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
  search_results: User[];
  sidebarOpen: boolean;
}

export * from "./pagination";
export * from "./user";
