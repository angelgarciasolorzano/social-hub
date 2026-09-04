import type {
  TrustedDeviceBrowserFilter,
  TrustedDeviceDeviceTypeFilter,
  TrustedDeviceLastAccessFilter,
  TrustedDevicePerPage,
  TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import type { TrustedDeviceSortKey } from "@/modules/setting/modules/trustedDevices/data/trustedDeviceSort";

export interface TrustedDevice {
  id: number;
  name: string | null;
  userAgent: string | null;
  browser: string | null;
  browserVersion: string | null;
  osName: string | null;
  osVersion: string | null;
  isMobile: boolean;
  ip: string | null;
  lastUsedAt: string | null;
  expiresAt: string;
  createdAt: string;
  deletedAt: string | null;
  isActive: boolean;
}

export type TrustedDeviceAction =
  "created" | "renewed" | "renamed" | "revoked" | "revoked_all" | "reactivated";

export interface TrustedDeviceStats {
  total: number;
  active: number;
  expiringSoon: number;
  recentlyAdded: number;
  inactive: number;
  revoked: number;
}

export interface TrustedDeviceActivityItem {
  id: number;
  action: TrustedDeviceAction;
  actionLabel: string;
  deviceId: number | null;
  deviceLabel: string | null;
  ip: string | null;
  createdAt: string | null;
}

export interface TrustedDevicePaginationLink {
  url: string | null;
  label: string;
  page: number;
  active: boolean;
}

export interface TrustedDevicePagination {
  current_page: number;
  data: TrustedDevice[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  links: TrustedDevicePaginationLink[];
  next_page_url: string | null;
  path: string | null;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface TrustedDeviceFilters {
  search: string;
  status: TrustedDeviceStatusFilter[] | null;
  browser: TrustedDeviceBrowserFilter[] | null;
  deviceType: TrustedDeviceDeviceTypeFilter | null;
  lastAccess: TrustedDeviceLastAccessFilter[] | null;
  sort: TrustedDeviceSortKey;
  perPage: TrustedDevicePerPage;
}
