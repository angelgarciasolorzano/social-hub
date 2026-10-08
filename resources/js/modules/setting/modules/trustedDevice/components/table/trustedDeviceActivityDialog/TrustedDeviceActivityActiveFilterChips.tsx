import type { JSX } from "react";

import {
  findTrustedDeviceFilterOptionLabel,
  removeTrustedDeviceFilterValue,
  type TrustedDeviceActiveFilterChip,
  TrustedDeviceActiveFilterChipGroup,
} from "@/modules/setting/modules/trustedDevice/components/table/TrustedDeviceActiveFilterChipGroup";
import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type { TrustedDeviceActivityFilterState } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceActivityFilters";

interface TrustedDeviceActivityActiveFilterChipsProps {
  filters: TrustedDeviceActivityFilterState;
  onSearchChange: (value: string) => void;
  onActionFilterChange: (value: TrustedDeviceActivityActionFilter[] | null) => void;
  onSinceDaysFilterChange: (value: TrustedDeviceActivitySinceDaysFilter[] | null) => void;
  onResetFilters: () => void;
}

export default function TrustedDeviceActivityActiveFilterChips({
  filters,
  onSearchChange,
  onActionFilterChange,
  onSinceDaysFilterChange,
  onResetFilters,
}: TrustedDeviceActivityActiveFilterChipsProps): JSX.Element | null {
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
    ...(filters.action ?? []).map((action) => ({
      key: `action-${action}`,
      label: `Acción: ${findTrustedDeviceFilterOptionLabel(action, activityActionOptions)}`,
      onRemove: () => {
        onActionFilterChange(removeTrustedDeviceFilterValue(filters.action, action));
      },
    })),
    ...(filters.sinceDays ?? []).map((sinceDays) => ({
      key: `since-days-${sinceDays}`,
      label: `Rango: ${findTrustedDeviceFilterOptionLabel(sinceDays, activitySinceDaysOptions)}`,
      onRemove: () => {
        onSinceDaysFilterChange(removeTrustedDeviceFilterValue(filters.sinceDays, sinceDays));
      },
    })),
  ];

  return (
    <TrustedDeviceActiveFilterChipGroup
      ariaLabel="Filtros activos de actividad"
      filterChips={activeFilterChips}
      onResetFilters={onResetFilters}
    />
  );
}
