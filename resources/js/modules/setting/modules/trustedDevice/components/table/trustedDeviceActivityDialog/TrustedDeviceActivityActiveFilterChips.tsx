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

import { useTranslation } from "@/shared/hooks";

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
    ...(filters.action ?? []).map((action) => ({
      key: `action-${action}`,
      label: t("setting/trusted-device-dialogs.activity.chip_action", {
        value: t(findTrustedDeviceFilterOptionLabel(action, activityActionOptions)),
      }),
      onRemove: () => {
        onActionFilterChange(removeTrustedDeviceFilterValue(filters.action, action));
      },
    })),
    ...(filters.sinceDays ?? []).map((sinceDays) => ({
      key: `since-days-${sinceDays}`,
      label: t("setting/trusted-device-dialogs.activity.chip_range", {
        value: t(findTrustedDeviceFilterOptionLabel(sinceDays, activitySinceDaysOptions)),
      }),
      onRemove: () => {
        onSinceDaysFilterChange(removeTrustedDeviceFilterValue(filters.sinceDays, sinceDays));
      },
    })),
  ];

  return (
    <TrustedDeviceActiveFilterChipGroup
      ariaLabel={t("setting/trusted-device-dialogs.activity.active_filters")}
      filterChips={activeFilterChips}
      onResetFilters={onResetFilters}
    />
  );
}
