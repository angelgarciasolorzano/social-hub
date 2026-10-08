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
  const activeFilterChips: readonly TrustedDeviceActiveFilterChip[] = [
    ...(filters.search === ""
      ? []
      : [
          {
            key: "search",
            label: `Búsqueda: ${filters.search}`,
            onRemove: () => {
              onSearchChange("");
            },
          },
        ]),
    ...(filters.status ?? []).map((status) => ({
      key: `status-${status}`,
      label: `Estado: ${findTrustedDeviceFilterOptionLabel(status, statusOptions)}`,
      onRemove: () => {
        onStatusFilterChange(removeTrustedDeviceFilterValue(filters.status, status));
      },
    })),
    ...(filters.browser ?? []).map((browser) => ({
      key: `browser-${browser}`,
      label: `Navegador / SO: ${findTrustedDeviceFilterOptionLabel(browser, browserOptions)}`,
      onRemove: () => {
        onBrowserFilterChange(removeTrustedDeviceFilterValue(filters.browser, browser));
      },
    })),
    ...(filters.deviceType === null
      ? []
      : [
          {
            key: "device-type",
            label: `Tipo: ${findTrustedDeviceFilterOptionLabel(filters.deviceType, deviceTypeOptions)}`,
            onRemove: () => {
              onDeviceTypeFilterChange(null);
            },
          },
        ]),
    ...(filters.lastAccess ?? []).map((lastAccess) => ({
      key: `last-access-${lastAccess}`,
      label: `Último acceso: ${findTrustedDeviceFilterOptionLabel(lastAccess, lastAccessOptions)}`,
      onRemove: () => {
        onLastAccessFilterChange(removeTrustedDeviceFilterValue(filters.lastAccess, lastAccess));
      },
    })),
  ];

  return (
    <TrustedDeviceActiveFilterChipGroup
      ariaLabel="Filtros activos"
      filterChips={activeFilterChips}
      onResetFilters={onResetFilters}
    />
  );
}

export default TrustedDeviceActiveFilterChips;
