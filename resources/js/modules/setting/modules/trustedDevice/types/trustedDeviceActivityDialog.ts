import type {
  TrustedDeviceActivityActionFilter,
  TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type { TrustedDeviceAction } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

import type { LengthAwarePagination } from "@/shared/types";

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

export type TrustedDeviceActivityPagination = LengthAwarePagination<TrustedDeviceActivityEvent>;
