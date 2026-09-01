import type { JSX } from "react";

import { ArrowDownNarrowWide, Funnel, RefreshCw, RotateCcw, Search } from "lucide-react";

import {
  browserOptions,
  deviceTypeOptions,
  lastAccessOptions,
  statusOptions,
  type TrustedDeviceBrowserFilter,
  type TrustedDeviceDeviceTypeFilter,
  type TrustedDeviceLastAccessFilter,
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
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/shared/components/shadcn/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/shared/components/shadcn/ui/select";
import { Separator } from "@/shared/components/shadcn/ui/separator";

interface TrustedDevicesTableToolbarProps {
  filters: TrustedDeviceFilters;
  onSearchChange: (value: string) => void;
  onStatusFilterChange: (value: TrustedDeviceStatusFilter | null) => void;
  onBrowserFilterChange: (value: TrustedDeviceBrowserFilter | null) => void;
  onDeviceTypeFilterChange: (value: TrustedDeviceDeviceTypeFilter | null) => void;
  onLastAccessFilterChange: (value: TrustedDeviceLastAccessFilter | null) => void;
  onSortOrderChange: (value: TrustedDeviceSortKey) => void;
  onResetFilters: () => void;
}

function TrustedDevicesTableToolbar(props: TrustedDevicesTableToolbarProps): JSX.Element {
  const {
    filters,
    onSearchChange,
    onStatusFilterChange,
    onBrowserFilterChange,
    onDeviceTypeFilterChange,
    onLastAccessFilterChange,
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
          deviceTypeFilter={filters.deviceType}
          lastAccessFilter={filters.lastAccess}
          onBrowserFilterChange={onBrowserFilterChange}
          onDeviceTypeFilterChange={onDeviceTypeFilterChange}
          onLastAccessFilterChange={onLastAccessFilterChange}
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

type TrustedDevicesFiltersPopoverProps = Omit<
  TrustedDevicesTableToolbarProps,
  "onSearchChange" | "onSortOrderChange" | "filters"
> & {
  statusFilter: TrustedDeviceStatusFilter | null;
  browserFilter: TrustedDeviceBrowserFilter | null;
  deviceTypeFilter: TrustedDeviceDeviceTypeFilter | null;
  lastAccessFilter: TrustedDeviceLastAccessFilter | null;
};

interface FilterSelectConfig {
  label: string;
  onChange: (value: string | null) => void;
  options: readonly { readonly label: string; readonly value: string }[];
  value: string | null;
}

function TrustedDevicesFiltersPopover(props: TrustedDevicesFiltersPopoverProps): JSX.Element {
  const {
    statusFilter,
    onStatusFilterChange,
    browserFilter,
    onBrowserFilterChange,
    deviceTypeFilter,
    onDeviceTypeFilterChange,
    lastAccessFilter,
    onLastAccessFilterChange,
    onResetFilters,
  } = props;

  const NO_FILTER = "__none__" as const;

  const filterConfigs: readonly FilterSelectConfig[] = [
    {
      label: "Estado",
      onChange: (value) => {
        onStatusFilterChange(value as TrustedDeviceStatusFilter | null);
      },
      options: statusOptions,
      value: statusFilter,
    },
    {
      label: "Navegador / SO",
      onChange: (value) => {
        onBrowserFilterChange(value as TrustedDeviceBrowserFilter | null);
      },
      options: browserOptions,
      value: browserFilter,
    },
    {
      label: "Tipo de dispositivo",
      onChange: (value) => {
        onDeviceTypeFilterChange(value as TrustedDeviceDeviceTypeFilter | null);
      },
      options: deviceTypeOptions,
      value: deviceTypeFilter,
    },
    {
      label: "Último acceso",
      onChange: (value) => {
        onLastAccessFilterChange(value as TrustedDeviceLastAccessFilter | null);
      },
      options: lastAccessOptions,
      value: lastAccessFilter,
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

              <Select
                value={config.value ?? ""}
                onValueChange={(next) => {
                  config.onChange(next === NO_FILTER || next === "" ? null : next);
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={config.label} />
                </SelectTrigger>

                <SelectContent>
                  <SelectItem value={NO_FILTER}>Sin filtro</SelectItem>

                  {config.options.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
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
