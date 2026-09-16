import type {
  TrustedDeviceActivityActionFilter,
  TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type {
  TrustedDeviceAction,
  TrustedDevicePaginationLink,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

export interface TrustedDeviceActivityFilters {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter[] | null;
  search: string;
}

export interface TrustedDeviceActivityEvent {
  id: number;
  action: TrustedDeviceAction;
  actionLabel: string;
  deviceId: number | null;
  deviceLabel: string | null;
  deviceIsMobile: boolean | null;
  deviceOsName: string | null;
  ip: string | null;
  createdAt: string | null;
}

export interface TrustedDeviceActivityPagination {
  current_page: number;
  data: TrustedDeviceActivityEvent[];
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
