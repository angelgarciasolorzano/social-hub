export interface BreadcrumbItem {
  href: string;
  title: string;
}

export interface SharedData {
  [key: string]: unknown;
  auth: Auth;
  name: string;
  posts: PostData;
  quote: { message: string; author: string };
  search_results: User[];
  sidebarOpen: boolean;
}
