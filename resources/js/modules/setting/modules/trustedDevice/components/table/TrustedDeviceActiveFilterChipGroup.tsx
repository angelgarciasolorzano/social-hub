import type { JSX } from "react";

import { X } from "lucide-react";

import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";

export interface TrustedDeviceActiveFilterChip {
  readonly key: string;
  readonly label: string;
  readonly onRemove: () => void;
}

export interface TrustedDeviceFilterOption {
  readonly label: string;
  readonly value: string;
}

interface TrustedDeviceActiveFilterChipGroupProps {
  ariaLabel: string;
  filterChips: readonly TrustedDeviceActiveFilterChip[];
  onResetFilters: () => void;
}

export function findTrustedDeviceFilterOptionLabel(
  value: string,
  options: readonly TrustedDeviceFilterOption[],
): string {
  return options.find((option) => option.value === value)?.label ?? value;
}

export function removeTrustedDeviceFilterValue<TValue>(
  values: readonly TValue[] | null,
  valueToRemove: TValue,
): TValue[] | null {
  const remainingValues = (values ?? []).filter((value) => value !== valueToRemove);

  return remainingValues.length > 0 ? remainingValues : null;
}

export function TrustedDeviceActiveFilterChipGroup({
  ariaLabel,
  filterChips,
  onResetFilters,
}: TrustedDeviceActiveFilterChipGroupProps): JSX.Element | null {
  if (filterChips.length === 0) {
    return null;
  }

  return (
    <div aria-label={ariaLabel} className="flex flex-wrap items-center gap-2" role="group">
      <span className="text-sm font-normal text-muted-foreground">Filtros activos:</span>

      {filterChips.map((filterChip) => (
        <TrustedDeviceActiveFilterChip
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

interface TrustedDeviceActiveFilterChipProps {
  label: string;
  onRemove: () => void;
}

function TrustedDeviceActiveFilterChip({
  label,
  onRemove,
}: TrustedDeviceActiveFilterChipProps): JSX.Element {
  return (
    <Badge className="gap-1.5 pr-1 text-xs font-medium" variant="secondary">
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
