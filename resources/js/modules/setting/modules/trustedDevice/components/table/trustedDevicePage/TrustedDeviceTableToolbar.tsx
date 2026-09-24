import type { JSX } from "react";

import { router } from "@inertiajs/react";

import type { ColumnVisibilityState, OnChangeFn } from "@tanstack/react-table";
import {
  ArrowDownNarrowWide,
  Columns3,
  Funnel,
  RefreshCw,
  RotateCcw,
  Search,
  X,
} from "lucide-react";

import TrustedDeviceActiveFilterChips, {
  type TrustedDeviceActiveFilterChipsProps,
} from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceActiveFilterChips";
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
import {
  defaultTrustedDeviceSort,
  type TrustedDeviceSortKey,
  trustedDeviceSortOptions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceSort";
import { trustedDeviceColumnVisibilityOptions } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns";
import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Checkbox } from "@/shared/components/shadcn/ui/checkbox";
import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxItem,
  ComboboxList,
  ComboboxTrigger,
  ComboboxValue,
  useComboboxAnchor,
} from "@/shared/components/shadcn/ui/combobox";
import {
  Field,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSet,
} from "@/shared/components/shadcn/ui/field";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/shared/components/shadcn/ui/radio-group";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";

interface TrustedDeviceTableToolbarProps extends Omit<
  TrustedDeviceActiveFilterChipsProps,
  "filters"
> {
  committedFilters: TrustedDeviceFilters;
  filters: TrustedDeviceFilters;
  columnVisibility: ColumnVisibilityState;
  onColumnVisibilityChange: OnChangeFn<ColumnVisibilityState>;
  onSortOrderChange: (value: TrustedDeviceSortKey) => void;
}

function TrustedDeviceTableToolbar(props: TrustedDeviceTableToolbarProps): JSX.Element {
  const {
    committedFilters,
    filters,
    columnVisibility,
    onColumnVisibilityChange,
    onSearchChange,
    onStatusFilterChange,
    onBrowserFilterChange,
    onDeviceTypeFilterChange,
    onLastAccessFilterChange,
    onSortOrderChange,
    onResetFilters,
  } = props;

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-4">
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

          {filters.search !== "" && (
            <InputGroupAddon align="inline-end">
              <InputGroupButton
                aria-label="Limpiar búsqueda"
                onClick={() => {
                  onSearchChange("");
                }}
                size="icon-xs"
              >
                <X />
              </InputGroupButton>
            </InputGroupAddon>
          )}
        </InputGroup>

        <div className="flex items-center justify-center gap-2">
          <TrustedDeviceFiltersPopover
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

          <TrustedDeviceSortPopover
            onSortOrderChange={onSortOrderChange}
            sortOrder={filters.sort}
          />

          <TrustedDeviceColumnVisibilityMenu
            columnVisibility={columnVisibility}
            onColumnVisibilityChange={onColumnVisibilityChange}
          />

          <Button
            type="button"
            variant="outline"
            onClick={() => {
              router.reload({
                only: ["trustedDevices", "stats", "recentActivity"],
              });
            }}
          >
            <RefreshCw />
            <span className="sr-only">Reload data</span>
          </Button>
        </div>
      </div>

      <TrustedDeviceActiveFilterChips
        filters={committedFilters}
        onBrowserFilterChange={onBrowserFilterChange}
        onDeviceTypeFilterChange={onDeviceTypeFilterChange}
        onLastAccessFilterChange={onLastAccessFilterChange}
        onResetFilters={onResetFilters}
        onSearchChange={onSearchChange}
        onStatusFilterChange={onStatusFilterChange}
      />
    </div>
  );
}

interface TrustedDeviceColumnVisibilityMenuProps {
  columnVisibility: ColumnVisibilityState;
  onColumnVisibilityChange: OnChangeFn<ColumnVisibilityState>;
}

function TrustedDeviceColumnVisibilityMenu({
  columnVisibility,
  onColumnVisibilityChange,
}: TrustedDeviceColumnVisibilityMenuProps): JSX.Element {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline">
          <Columns3 />
          Columnas
        </Button>
      </PopoverTrigger>

      <PopoverContent align="end" className="w-60 p-3">
        <FieldSet className="gap-2">
          <FieldLegend className="mb-0 font-bold" variant="label">
            Columnas visibles
          </FieldLegend>

          <FieldGroup className="mt-4 gap-2.5">
            {trustedDeviceColumnVisibilityOptions.map((columnOption) => {
              const checkboxId = `trusted-device-column-${columnOption.id}`;

              return (
                <Field className="gap-2" key={columnOption.id} orientation="horizontal">
                  <Checkbox
                    checked={columnVisibility[columnOption.id] !== false}
                    id={checkboxId}
                    onCheckedChange={(checked) => {
                      onColumnVisibilityChange((currentColumnVisibility) => ({
                        ...currentColumnVisibility,
                        [columnOption.id]: checked === true,
                      }));
                    }}
                  />
                  <FieldLabel className="cursor-pointer font-normal" htmlFor={checkboxId}>
                    {columnOption.label}
                  </FieldLabel>
                </Field>
              );
            })}
          </FieldGroup>
        </FieldSet>

        <Separator className="my-3" />

        <Button
          className="w-full justify-start"
          onClick={() => {
            onColumnVisibilityChange({});
          }}
          size="sm"
          type="button"
          variant="ghost"
        >
          <RotateCcw data-icon="inline-start" />
          Restablecer columnas
        </Button>
      </PopoverContent>
    </Popover>
  );
}

type TrustedDeviceFiltersPopoverProps = Omit<
  TrustedDeviceTableToolbarProps,
  | "committedFilters"
  | "columnVisibility"
  | "onColumnVisibilityChange"
  | "onSearchChange"
  | "onSortOrderChange"
  | "filters"
> & {
  statusFilter: TrustedDeviceStatusFilter[] | null;
  browserFilter: TrustedDeviceBrowserFilter[] | null;
  deviceTypeFilter: TrustedDeviceDeviceTypeFilter | null;
  lastAccessFilter: TrustedDeviceLastAccessFilter[] | null;
};

interface FilterOption {
  readonly label: string;
  readonly value: string;
}

interface FilterComboboxConfig {
  label: string;
  multiple: boolean;
  options: readonly FilterOption[];
  value: string | readonly string[] | null;
  onChange: (value: string | readonly string[] | null) => void;
}

function TrustedDeviceFiltersPopover(props: TrustedDeviceFiltersPopoverProps): JSX.Element {
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

  const filterConfigs: readonly FilterComboboxConfig[] = [
    {
      label: "Estado",
      multiple: true,
      options: statusOptions,
      value: statusFilter,
      onChange: (value) => {
        if (value === null) {
          onStatusFilterChange(null);
        } else if (typeof value === "string") {
          onStatusFilterChange([value as TrustedDeviceStatusFilter]);
        } else {
          onStatusFilterChange([...value] as TrustedDeviceStatusFilter[]);
        }
      },
    },
    {
      label: "Navegador / SO",
      multiple: true,
      options: browserOptions,
      value: browserFilter,
      onChange: (value) => {
        if (value === null) {
          onBrowserFilterChange(null);
        } else if (typeof value === "string") {
          onBrowserFilterChange([value as TrustedDeviceBrowserFilter]);
        } else {
          onBrowserFilterChange([...value] as TrustedDeviceBrowserFilter[]);
        }
      },
    },
    {
      label: "Tipo de dispositivo",
      multiple: false,
      options: deviceTypeOptions,
      value: deviceTypeFilter,
      onChange: (value) => {
        onDeviceTypeFilterChange(value as TrustedDeviceDeviceTypeFilter | null);
      },
    },
    {
      label: "Último acceso",
      multiple: true,
      options: lastAccessOptions,
      value: lastAccessFilter,
      onChange: (value) => {
        if (value === null) {
          onLastAccessFilterChange(null);
        } else if (typeof value === "string") {
          onLastAccessFilterChange([value as TrustedDeviceLastAccessFilter]);
        } else {
          onLastAccessFilterChange([...value] as TrustedDeviceLastAccessFilter[]);
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
            <FilterCombobox
              key={config.label}
              label={config.label}
              multiple={config.multiple}
              onChange={config.onChange}
              options={config.options}
              value={config.value}
            />
          ))}

          <Button type="button" variant="outline" onClick={onResetFilters}>
            Limpiar filtros
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}

function FilterCombobox({
  label,
  multiple,
  options,
  value,
  onChange,
}: FilterComboboxConfig): JSX.Element {
  const anchor = useComboboxAnchor();

  const findLabel = (value: string, options: readonly FilterOption[]): string => {
    return options.find((opt) => opt.value === value)?.label ?? value;
  };

  const isStringArray = (value: string | readonly string[] | null): value is readonly string[] => {
    return Array.isArray(value);
  };

  if (multiple) {
    const arr: string[] = isStringArray(value) ? [...value] : value === null ? [] : [value];

    return (
      <div className="flex flex-col gap-1.5">
        <label className="text-xs font-medium text-muted-foreground">{label}</label>

        <Combobox
          items={options}
          multiple
          onValueChange={(next: string[]) => {
            onChange(next);
          }}
          value={arr}
        >
          <ComboboxChips className="min-h-9 w-full" ref={anchor}>
            <ComboboxValue>
              {(values: string[]) => (
                <>
                  {values.map((selected) => (
                    <ComboboxChip key={selected}>{findLabel(selected, options)}</ComboboxChip>
                  ))}
                  <ComboboxChipsInput placeholder={arr.length > 0 ? "" : label} />
                </>
              )}
            </ComboboxValue>
          </ComboboxChips>

          <ComboboxContent anchor={anchor}>
            <ComboboxEmpty>Sin resultados</ComboboxEmpty>
            <ComboboxList>
              {(item: { label: string; value: string }) => (
                <ComboboxItem key={item.value} value={item.value}>
                  {item.label}
                </ComboboxItem>
              )}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      </div>
    );
  }

  const single = typeof value === "string" ? value : null;
  const singleLabel = single === null ? null : findLabel(single, options);

  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-xs font-medium text-muted-foreground">{label}</label>

      <Combobox
        value={single}
        onValueChange={(next) => {
          onChange(next ?? null);
        }}
      >
        <ComboboxTrigger
          className={cn(
            "flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs",
            "data-popup-open:border-ring data-popup-open:ring-[3px] data-popup-open:ring-ring/50",
          )}
        >
          <span className={cn("truncate", singleLabel === null && "text-muted-foreground")}>
            {singleLabel ?? label}
          </span>
        </ComboboxTrigger>

        <ComboboxContent>
          <ComboboxList>
            {options.map((opt) => (
              <ComboboxItem key={opt.value} value={opt.value}>
                {opt.label}
              </ComboboxItem>
            ))}
          </ComboboxList>
        </ComboboxContent>
      </Combobox>
    </div>
  );
}

interface TrustedDeviceSortPopoverProps {
  sortOrder: TrustedDeviceSortKey;
  onSortOrderChange: (value: TrustedDeviceSortKey) => void;
}

function TrustedDeviceSortPopover({
  sortOrder,
  onSortOrderChange,
}: TrustedDeviceSortPopoverProps): JSX.Element {
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

export default TrustedDeviceTableToolbar;
