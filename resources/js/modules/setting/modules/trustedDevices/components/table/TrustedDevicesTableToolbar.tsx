import type { JSX } from "react";

import { ArrowDownNarrowWide, Funnel, RefreshCw, RotateCcw, Search } from "lucide-react";

import {
  browserOptions,
  statusOptions,
  type TrustedDeviceBrowserFilter,
  type TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import {
  defaultTrustedDeviceSort,
  type TrustedDeviceSortKey,
  trustedDeviceSortOptions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceSort";
import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Combobox,
  ComboboxContent,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/shared/components/shadcn/ui/combobox";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/shared/components/shadcn/ui/radio-group";
import { Separator } from "@/shared/components/shadcn/ui/separator";

interface TrustedDevicesTableToolbarProps {
  filters: TrustedDeviceFilters;
  onSearchChange: (value: string) => void;
  onStatusFilterChange: (value: TrustedDeviceStatusFilter | null) => void;
  onBrowserFilterChange: (value: TrustedDeviceBrowserFilter | null) => void;
  onSortOrderChange: (value: TrustedDeviceSortKey) => void;
  onResetFilters: () => void;
}

function TrustedDevicesTableToolbar(props: TrustedDevicesTableToolbarProps): JSX.Element {
  const {
    filters,
    onSearchChange,
    onStatusFilterChange,
    onBrowserFilterChange,
    onSortOrderChange,
    onResetFilters,
  } = props;

  return (
    <div className="flex items-center justify-between">
      <InputGroup className="max-w-xs">
        <InputGroupAddon>
          <Search className="size-4" />
        </InputGroupAddon>
        <InputGroupInput
          placeholder="Buscar dispositivo..."
          value={filters.search}
          onChange={(event) => {
            onSearchChange(event.target.value);
          }}
        />
      </InputGroup>

      <div className="flex items-center justify-center gap-2">
        <TrustedDevicesFiltersPopover
          browserFilter={filters.browser}
          onBrowserFilterChange={onBrowserFilterChange}
          onResetFilters={onResetFilters}
          onStatusFilterChange={onStatusFilterChange}
          statusFilter={filters.status}
        />

        <TrustedDevicesSortPopover onSortOrderChange={onSortOrderChange} sortOrder={filters.sort} />

        <Button variant="outline">
          <RefreshCw />
          <span className="sr-only">Reload data</span>
        </Button>
      </div>
    </div>
  );
}

type TrustedDevicesFiltersPopoverProps = Pick<
  TrustedDevicesTableToolbarProps,
  "onStatusFilterChange" | "onBrowserFilterChange" | "onResetFilters"
> & {
  statusFilter: TrustedDeviceStatusFilter | null;
  browserFilter: TrustedDeviceBrowserFilter | null;
};

interface TrustedDeviceFilterConfig<T extends string> {
  label: string;
  onChange: (value: T | null) => void;
  options: readonly { readonly label: string; readonly value: T }[];
  placeholder: string;
  value: T | null;
}

function TrustedDevicesFiltersPopover(props: TrustedDevicesFiltersPopoverProps): JSX.Element {
  const {
    statusFilter,
    onStatusFilterChange,
    browserFilter,
    onBrowserFilterChange,
    onResetFilters,
  } = props;

  const filterConfigs: readonly (
    | TrustedDeviceFilterConfig<TrustedDeviceStatusFilter>
    | TrustedDeviceFilterConfig<TrustedDeviceBrowserFilter>
  )[] = [
    {
      label: "Estado",
      placeholder: "Estado",
      value: statusFilter,
      onChange: onStatusFilterChange,
      options: statusOptions,
    },
    {
      label: "Navegador / SO",
      placeholder: "Navegador / SO",
      value: browserFilter,
      onChange: onBrowserFilterChange,
      options: browserOptions,
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
              type="button"
              variant="ghost"
              size="sm"
              className="text-blue-700 hover:bg-blue-100/50 hover:text-blue-700 dark:text-blue-500 dark:hover:bg-blue-900/20 dark:hover:text-blue-500"
              onClick={onResetFilters}
            >
              Restablecer
            </Button>
          </div>

          {filterConfigs.map((config) => (
            <div className="flex flex-col gap-1.5" key={config.label}>
              <label className="text-xs font-medium text-muted-foreground">{config.label}</label>

              <Combobox
                value={config.value}
                onValueChange={(next) => {
                  if (next === null) {
                    config.onChange(null);

                    return;
                  }

                  config.onChange(next as TrustedDeviceStatusFilter & TrustedDeviceBrowserFilter);
                }}
              >
                <ComboboxInput showClear placeholder={config.placeholder} />

                <ComboboxContent>
                  <ComboboxList>
                    {config.options.map((opt) => (
                      <ComboboxItem key={opt.value} value={opt.value}>
                        {opt.label}
                      </ComboboxItem>
                    ))}
                  </ComboboxList>
                </ComboboxContent>
              </Combobox>
            </div>
          ))}

          <Button type="button" variant="outline" onClick={onResetFilters}>
            Limpiar filtros
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}

interface TrustedDevicesSortPopoverProps {
  sortOrder: TrustedDeviceSortKey;
  onSortOrderChange: (value: TrustedDeviceSortKey) => void;
}

function TrustedDevicesSortPopover({
  sortOrder,
  onSortOrderChange,
}: TrustedDevicesSortPopoverProps): JSX.Element {
  const selectedSortLabel =
    trustedDeviceSortOptions.find((option) => option.value === sortOrder)?.label ?? "Ordenar por";

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline">
          <ArrowDownNarrowWide />
          {selectedSortLabel}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-3">
        <div className="flex flex-col gap-3">
          <h4 className="text-sm font-semibold">Ordenar por</h4>

          <RadioGroup
            value={sortOrder}
            onValueChange={(value) => {
              onSortOrderChange(value as TrustedDeviceSortKey);
            }}
            className="gap-1"
          >
            {trustedDeviceSortOptions.map((option) => (
              <label
                key={option.value}
                htmlFor={`sort-${option.value}`}
                className="flex cursor-pointer items-start gap-3 rounded-md p-2 transition-colors hover:bg-accent/50 has-data-[state=checked]:bg-blue-100/50 has-data-[state=checked]:dark:bg-blue-950/20"
              >
                <RadioGroupItem
                  className="mt-0.5"
                  id={`sort-${option.value}`}
                  value={option.value}
                />
                <div className="flex flex-1 flex-col gap-0.5 leading-tight">
                  <span className="text-sm font-medium">{option.label}</span>
                  <span className="text-xs text-muted-foreground">{option.description}</span>
                </div>
              </label>
            ))}
          </RadioGroup>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="flex flex-col leading-tight">
              <span className="text-sm font-medium">Predeterminado</span>
              <span className="text-xs text-muted-foreground">{selectedSortLabel}</span>
            </div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                onSortOrderChange(defaultTrustedDeviceSort);
              }}
            >
              <RotateCcw />
              Restablecer
            </Button>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  );
}

export default TrustedDevicesTableToolbar;
