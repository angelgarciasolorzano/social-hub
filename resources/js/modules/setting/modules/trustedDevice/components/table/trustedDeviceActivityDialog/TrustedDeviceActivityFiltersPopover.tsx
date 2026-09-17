import type { JSX } from "react";

import { Funnel } from "lucide-react";

import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";

import TrustedDeviceActivityFilterCombobox, {
  type FilterComboboxConfig,
} from "./TrustedDeviceActivityFilterCombobox";

export interface TrustedDeviceActivityFiltersPopoverProps {
  actionFilter: TrustedDeviceActivityActionFilter[] | null;
  sinceDaysFilter: TrustedDeviceActivitySinceDaysFilter[] | null;
  onActionFilterChange: (value: TrustedDeviceActivityActionFilter[] | null) => void;
  onSinceDaysFilterChange: (value: TrustedDeviceActivitySinceDaysFilter[] | null) => void;
  onResetFilters: () => void;
}

export default function TrustedDeviceActivityFiltersPopover(
  props: TrustedDeviceActivityFiltersPopoverProps,
): JSX.Element {
  const {
    actionFilter,
    sinceDaysFilter,
    onActionFilterChange,
    onSinceDaysFilterChange,
    onResetFilters,
  } = props;

  const filterConfigs: readonly FilterComboboxConfig[] = [
    {
      label: "Acción",
      multiple: true,
      options: activityActionOptions,
      value: actionFilter,
      onChange: (value) => {
        if (value === null) {
          onActionFilterChange([]);
        } else if (typeof value === "string") {
          onActionFilterChange([value as TrustedDeviceActivityActionFilter]);
        } else {
          onActionFilterChange([...value] as TrustedDeviceActivityActionFilter[]);
        }
      },
    },
    {
      label: "Filtrar por rango temporal",
      multiple: true,
      options: activitySinceDaysOptions,
      value: sinceDaysFilter,
      onChange: (value) => {
        if (value === null) {
          onSinceDaysFilterChange([]);
        } else if (typeof value === "string") {
          onSinceDaysFilterChange([value as TrustedDeviceActivitySinceDaysFilter]);
        } else {
          onSinceDaysFilterChange([...value] as TrustedDeviceActivitySinceDaysFilter[]);
        }
      },
    },
  ];

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline">
          <Funnel />
          Filtros
        </Button>
      </PopoverTrigger>

      <PopoverContent align="end" className="w-72 p-3">
        <div className="flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold">Filtros</h4>

            <Button
              className="text-blue-700 hover:bg-blue-100/50 hover:text-blue-700 dark:text-blue-500 dark:hover:bg-blue-900/20 dark:hover:text-blue-500"
              onClick={onResetFilters}
              size="sm"
              type="button"
              variant="ghost"
            >
              Restablecer
            </Button>
          </div>

          {filterConfigs.map((config) => (
            <TrustedDeviceActivityFilterCombobox
              key={config.label}
              label={config.label}
              multiple={config.multiple}
              onChange={config.onChange}
              options={config.options}
              value={config.value}
            />
          ))}

          <Button onClick={onResetFilters} type="button" variant="outline">
            Limpiar filtros
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}
