import type { JSX } from "react";

import { X } from "lucide-react";

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

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";

export interface TrustedDeviceActiveFilterChipsProps {
  filters: TrustedDeviceFilters;
  onSearchChange: (value: string) => void;
  onStatusFilterChange: (value: TrustedDeviceStatusFilter[] | null) => void;
  onBrowserFilterChange: (value: TrustedDeviceBrowserFilter[] | null) => void;
  onDeviceTypeFilterChange: (value: TrustedDeviceDeviceTypeFilter | null) => void;
  onLastAccessFilterChange: (value: TrustedDeviceLastAccessFilter[] | null) => void;
  onResetFilters: () => void;
}

interface TrustedDeviceActiveFilterChipData {
  key: string;
  label: string;
  onRemove: () => void;
}

interface TrustedDeviceActiveFilterChipProps {
  label: string;
  onRemove: () => void;
}

interface FilterOption {
  readonly label: string;
  readonly value: string;
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
  const activeFilterChips: readonly TrustedDeviceActiveFilterChipData[] = [
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
      label: `Estado: ${findFilterOptionLabel(status, statusOptions)}`,
      onRemove: () => {
        onStatusFilterChange(removeFilterValue(filters.status, status));
      },
    })),
    ...(filters.browser ?? []).map((browser) => ({
      key: `browser-${browser}`,
      label: `Navegador / SO: ${findFilterOptionLabel(browser, browserOptions)}`,
      onRemove: () => {
        onBrowserFilterChange(removeFilterValue(filters.browser, browser));
      },
    })),
    ...(filters.deviceType === null
      ? []
      : [
          {
            key: "device-type",
            label: `Tipo: ${findFilterOptionLabel(filters.deviceType, deviceTypeOptions)}`,
            onRemove: () => {
              onDeviceTypeFilterChange(null);
            },
          },
        ]),
    ...(filters.lastAccess ?? []).map((lastAccess) => ({
      key: `last-access-${lastAccess}`,
      label: `Último acceso: ${findFilterOptionLabel(lastAccess, lastAccessOptions)}`,
      onRemove: () => {
        onLastAccessFilterChange(removeFilterValue(filters.lastAccess, lastAccess));
      },
    })),
  ];

  if (activeFilterChips.length === 0) {
    return null;
  }

  return (
    <div aria-label="Filtros activos" className="flex flex-wrap items-center gap-2" role="group">
      <span className="text-sm text-muted-foreground">Filtros activos:</span>

      {activeFilterChips.map((filterChip) => (
        <TrustedDeviceActiveFilterChip
          key={filterChip.key}
          label={filterChip.label}
          onRemove={filterChip.onRemove}
        />
      ))}

      <Button type="button" variant="ghost" size="sm" onClick={onResetFilters}>
        Limpiar filtros
      </Button>
    </div>
  );
}

function TrustedDeviceActiveFilterChip({
  label,
  onRemove,
}: TrustedDeviceActiveFilterChipProps): JSX.Element {
  return (
    <Badge className="gap-1.5 pr-1" variant="secondary">
      <span>{label}</span>
      <Button
        aria-label={`Quitar filtro: ${label}`}
        className="-mr-0.5"
        onClick={onRemove}
        size="icon-xs"
        type="button"
        variant="ghost"
      >
        <X data-icon="inline-start" />
      </Button>
    </Badge>
  );
}

function findFilterOptionLabel(value: string, options: readonly FilterOption[]): string {
  return options.find((option) => option.value === value)?.label ?? value;
}

function removeFilterValue<TValue>(
  values: readonly TValue[] | null,
  valueToRemove: TValue,
): TValue[] | null {
  const remainingValues = (values ?? []).filter((value) => value !== valueToRemove);

  return remainingValues.length > 0 ? remainingValues : null;
}

export default TrustedDeviceActiveFilterChips;
