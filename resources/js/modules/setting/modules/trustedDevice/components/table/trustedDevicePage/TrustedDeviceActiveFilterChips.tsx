import type { JSX } from "react";

import {
  findTrustedDeviceFilterOptionLabel,
  removeTrustedDeviceFilterValue,
  type TrustedDeviceActiveFilterChip,
  TrustedDeviceActiveFilterChipGroup,
} from "@/modules/setting/modules/trustedDevice/components/table/TrustedDeviceActiveFilterChipGroup";
import {
  browserOptions,
  deviceTypeOptions,
  lastAccessOptions,
  statusOptions,
  type TrustedDeviceBrowserFilter,
  type TrustedDeviceDeviceTypeFilter,
  type TrustedDeviceLastAccessFilter,
  type TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

import { useTranslation } from "@/shared/hooks";

export interface TrustedDeviceActiveFilterChipsProps {
  filters: TrustedDeviceFilters;
  onSearchChange: (value: string) => void;
  onStatusFilterChange: (value: TrustedDeviceStatusFilter[] | null) => void;
  onBrowserFilterChange: (value: TrustedDeviceBrowserFilter[] | null) => void;
  onDeviceTypeFilterChange: (value: TrustedDeviceDeviceTypeFilter | null) => void;
  onLastAccessFilterChange: (value: TrustedDeviceLastAccessFilter[] | null) => void;
  onResetFilters: () => void;
}

function TrustedDeviceActiveFilterChips({
  filters,
  onSearchChange,
  onStatusFilterChange,
  onBrowserFilterChange,
  onDeviceTypeFilterChange,
  onLastAccessFilterChange,
  onResetFilters,
}: TrustedDeviceActiveFilterChipsProps): JSX.Element | null {
  const { t } = useTranslation();

  const activeFilterChips: readonly TrustedDeviceActiveFilterChip[] = [
    ...(filters.search === ""
      ? []
      : [
          {
            key: "search",
            label: t("setting/trusted-devices.chips.search", { value: filters.search }),
            onRemove: () => {
              onSearchChange("");
            },
          },
        ]),
    ...(filters.status ?? []).map((status) => ({
      key: `status-${status}`,
      label: t("setting/trusted-devices.chips.status", {
        value: t(findTrustedDeviceFilterOptionLabel(status, statusOptions)),
      }),
      onRemove: () => {
        onStatusFilterChange(removeTrustedDeviceFilterValue(filters.status, status));
      },
    })),
    ...(filters.browser ?? []).map((browser) => ({
      key: `browser-${browser}`,
      label: t("setting/trusted-devices.chips.browser_os", {
        value: t(findTrustedDeviceFilterOptionLabel(browser, browserOptions)),
      }),
      onRemove: () => {
        onBrowserFilterChange(removeTrustedDeviceFilterValue(filters.browser, browser));
      },
    })),
    ...(filters.deviceType === null
      ? []
      : [
          {
            key: "device-type",
            label: t("setting/trusted-devices.chips.device_type", {
              value: t(findTrustedDeviceFilterOptionLabel(filters.deviceType, deviceTypeOptions)),
            }),
            onRemove: () => {
              onDeviceTypeFilterChange(null);
            },
          },
        ]),
    ...(filters.lastAccess ?? []).map((lastAccess) => ({
      key: `last-access-${lastAccess}`,
      label: t("setting/trusted-devices.chips.last_access", {
        value: t(findTrustedDeviceFilterOptionLabel(lastAccess, lastAccessOptions)),
      }),
      onRemove: () => {
        onLastAccessFilterChange(removeTrustedDeviceFilterValue(filters.lastAccess, lastAccess));
      },
    })),
  ];

  return (
    <TrustedDeviceActiveFilterChipGroup
      ariaLabel={t("setting/trusted-devices.chips.active_filters")}
      filterChips={activeFilterChips}
      onResetFilters={onResetFilters}
    />
  );
}

export default TrustedDeviceActiveFilterChips;
