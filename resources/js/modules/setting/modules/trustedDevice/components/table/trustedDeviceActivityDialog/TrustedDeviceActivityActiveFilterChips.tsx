import type { JSX } from "react";

import { X } from "lucide-react";

import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type { TrustedDeviceActivityFilterState } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceActivityFilters";

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";

interface TrustedDeviceActivityActiveFilterChipsProps {
  filters: TrustedDeviceActivityFilterState;
  onSearchChange: (value: string) => void;
  onActionFilterChange: (value: TrustedDeviceActivityActionFilter[] | null) => void;
  onSinceDaysFilterChange: (value: TrustedDeviceActivitySinceDaysFilter[] | null) => void;
  onResetFilters: () => void;
}

interface TrustedDeviceActivityActiveFilterChipData {
  key: string;
  label: string;
  onRemove: () => void;
}

interface TrustedDeviceActivityActiveFilterChipProps {
  label: string;
  onRemove: () => void;
}

interface FilterOption {
  readonly label: string;
  readonly value: string;
}

export default function TrustedDeviceActivityActiveFilterChips({
  filters,
  onSearchChange,
  onActionFilterChange,
  onSinceDaysFilterChange,
  onResetFilters,
}: TrustedDeviceActivityActiveFilterChipsProps): JSX.Element | null {
  const activeFilterChips: readonly TrustedDeviceActivityActiveFilterChipData[] = [
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
      label: `Acción: ${findFilterOptionLabel(action, activityActionOptions)}`,
      onRemove: () => {
        onActionFilterChange(removeFilterValue(filters.action, action));
      },
    })),
    ...(filters.sinceDays ?? []).map((sinceDays) => ({
      key: `since-days-${sinceDays}`,
      label: `Rango: ${findFilterOptionLabel(sinceDays, activitySinceDaysOptions)}`,
      onRemove: () => {
        onSinceDaysFilterChange(removeFilterValue(filters.sinceDays, sinceDays));
      },
    })),
  ];

  if (activeFilterChips.length === 0) {
    return null;
  }

  return (
    <div
      aria-label="Filtros activos de actividad"
      className="flex flex-wrap items-center gap-2"
      role="group"
    >
      <span className="text-sm text-muted-foreground">Filtros activos:</span>

      {activeFilterChips.map((filterChip) => (
        <TrustedDeviceActivityActiveFilterChip
          key={filterChip.key}
          label={filterChip.label}
          onRemove={filterChip.onRemove}
        />
      ))}

      <Button onClick={onResetFilters} size="sm" type="button" variant="ghost">
        Limpiar filtros
      </Button>
    </div>
  );
}

function TrustedDeviceActivityActiveFilterChip({
  label,
  onRemove,
}: TrustedDeviceActivityActiveFilterChipProps): JSX.Element {
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
