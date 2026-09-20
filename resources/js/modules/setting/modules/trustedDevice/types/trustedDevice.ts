import type {
  TrustedDeviceBrowserFilter,
  TrustedDeviceDeviceTypeFilter,
  TrustedDeviceLastAccessFilter,
  TrustedDevicePerPage,
  TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import type { TrustedDeviceSortKey } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceSort";

import type { LengthAwarePagination } from "@/shared/types";

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
  byDeviceType: { desktop: number; mobile: number };
}

export interface TrustedDeviceActivityItem {
  id: number;
  action: TrustedDeviceAction;
  actionLabel: string;
  deviceLabel: string | null;
  ip: string | null;
  createdAt: string | null;
}

export type TrustedDevicePagination = LengthAwarePagination<TrustedDevice>;

export interface TrustedDeviceFilters {
  search: string;
  status: TrustedDeviceStatusFilter[] | null;
  browser: TrustedDeviceBrowserFilter[] | null;
  deviceType: TrustedDeviceDeviceTypeFilter | null;
  lastAccess: TrustedDeviceLastAccessFilter[] | null;
  sort: TrustedDeviceSortKey;
  perPage: TrustedDevicePerPage;
}
