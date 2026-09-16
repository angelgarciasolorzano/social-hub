import type { JSX } from "react";
import { useEffect, useRef, useState } from "react";

import { router, usePage } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";

import {
  ChevronLeft,
  ChevronRight,
  Clock,
  Funnel,
  Inbox,
  Info,
  LoaderCircle,
  MapPin,
  Pencil,
  RefreshCw,
  RotateCw,
  Search,
  SearchX,
  Trash2,
  UserPlus,
  X,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import { useActivityFilters } from "@/modules/setting/modules/trustedDevice/hooks/useActivityFilters";
import type { TrustedDeviceAction } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type {
  TrustedDeviceActivityEvent,
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPagination,
} from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";
import { computePaginationRange } from "@/modules/setting/modules/trustedDevice/utils/pagination";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { Button } from "@/shared/components/shadcn/ui/button";
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
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

interface TrustedDeviceActivityDialogProps {
  open: boolean;
  onClose: () => void;
}

interface TrustedDeviceActivityPayload {
  activityLog: TrustedDeviceActivityPagination;
  activityFilters: TrustedDeviceActivityFilters;
}

interface TrustedDeviceActivityDialogPageProps extends SharedData {
  activityDialog?: TrustedDeviceActivityPayload;
}

const actionVisuals: Record<TrustedDeviceAction, { icon: LucideIcon; color: IconColorVariant }> = {
  created: { icon: UserPlus, color: "blue" },
  renewed: { icon: RefreshCw, color: "green" },
  renamed: { icon: Pencil, color: "purple" },
  revoked: { icon: Trash2, color: "red" },
  revoked_all: { icon: Trash2, color: "red" },
  reactivated: { icon: RotateCw, color: "green" },
};

export default function TrustedDeviceActivityDialog({
  open,
  onClose,
}: TrustedDeviceActivityDialogProps): JSX.Element {
  const { activityDialog } = usePage<TrustedDeviceActivityDialogPageProps>().props;

  const hasRequestedActivityRef = useRef(false);
  const [hasFreshActivity, setHasFreshActivity] = useState(false);

  useEffect(() => {
    if (hasRequestedActivityRef.current) return;

    hasRequestedActivityRef.current = true;

    router.reload({
      only: ["activityDialog"],
      onFinish: () => {
        setHasFreshActivity(true);
      },
    });
  }, []);

  const isLoading = !hasFreshActivity || activityDialog === undefined;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>
            <div className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-muted-foreground" />
              Actividad reciente de dispositivos
            </div>
          </DialogTitle>
          <DialogDescription>
            Historial de eventos relacionados con tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="flex min-h-60 items-center justify-center">
            <LoaderCircle className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : (
          <TrustedDeviceActivityDialogBody
            initialActivity={activityDialog.activityLog}
            initialFilters={activityDialog.activityFilters}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

interface TrustedDeviceActivityDialogBodyProps {
  initialActivity: TrustedDeviceActivityPagination;
  initialFilters: TrustedDeviceActivityFilters;
}

function TrustedDeviceActivityDialogBody({
  initialActivity,
  initialFilters,
}: TrustedDeviceActivityDialogBodyProps): JSX.Element {
  const { committedFilters, filters, goToPage, resetFilters, updateFilter } =
    useActivityFilters(initialFilters);

  const hasActiveFilters =
    committedFilters.search !== "" ||
    (committedFilters.action !== null && committedFilters.action.length > 0) ||
    (committedFilters.sinceDays !== null && committedFilters.sinceDays.length > 0);

  return (
    <>
      <div className="flex items-center gap-2">
        <InputGroup className="flex-1">
          <InputGroupAddon>
            <Search />
          </InputGroupAddon>

          <InputGroupInput
            onChange={(event) => {
              updateFilter("search", event.target.value);
            }}
            placeholder="Buscar por dispositivo, nombre o IP..."
            value={filters.search}
          />

          {filters.search !== "" && (
            <InputGroupAddon align="inline-end">
              <InputGroupButton
                aria-label="Limpiar búsqueda"
                onClick={() => {
                  updateFilter("search", "");
                }}
                size="icon-xs"
              >
                <X />
              </InputGroupButton>
            </InputGroupAddon>
          )}
        </InputGroup>

        <ActivityFiltersPopover
          actionFilter={filters.action}
          onActionFilterChange={(value) => {
            updateFilter("action", value);
          }}
          onResetFilters={resetFilters}
          onSinceDaysFilterChange={(value) => {
            updateFilter("sinceDays", value);
          }}
          sinceDaysFilter={filters.sinceDays}
        />
      </div>

      <div className="max-h-[60vh] rounded-xl border dark:bg-muted/20">
        {initialActivity.data.length === 0 ? (
          <div className="gap-2 p-6">
            {hasActiveFilters ? (
              <EmptyState
                description="Prueba cambiar el rango temporal, el tipo de acción o el termino de busqueda."
                icon={SearchX}
                title="Sin actividad para los filtros seleccionados."
              />
            ) : (
              <EmptyState
                description="Los eventos de tus dispositivos de confianza aparecerán aquí."
                icon={Inbox}
                title="Aún no hay actividad registrada."
              />
            )}
          </div>
        ) : (
          <ul className="divide-y">
            {initialActivity.data.map((event) => (
              <TrustedDeviceActivityDialogEventRow event={event} key={event.id} />
            ))}
          </ul>
        )}
      </div>

      <DialogFooter className="flex items-center justify-between sm:justify-between">
        <div className="flex items-center gap-2">
          <Info className="h-5 w-5 text-muted-foreground" />

          <span className="text-xs text-muted-foreground">
            {initialActivity.total === 0
              ? "Sin eventos"
              : `Mostrando ${initialActivity.from ?? 0}-${initialActivity.to ?? 0} de ${initialActivity.total} eventos`}
          </span>
        </div>

        <div className="flex items-center gap-3">
          {initialActivity.last_page > 1 && (
            <ActivityPagination onPageChange={goToPage} pagination={initialActivity} />
          )}

          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </div>
      </DialogFooter>
    </>
  );
}

interface ActivityFiltersPopoverProps {
  actionFilter: TrustedDeviceActivityActionFilter[] | null;
  sinceDaysFilter: TrustedDeviceActivitySinceDaysFilter[] | null;
  onActionFilterChange: (value: TrustedDeviceActivityActionFilter[] | null) => void;
  onSinceDaysFilterChange: (value: TrustedDeviceActivitySinceDaysFilter[] | null) => void;
  onResetFilters: () => void;
}

function ActivityFiltersPopover(props: ActivityFiltersPopoverProps): JSX.Element {
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
            <ActivityFilterCombobox
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

function ActivityFilterCombobox(props: FilterComboboxConfig): JSX.Element {
  const { label, multiple, options, value, onChange } = props;

  const anchor = useComboboxAnchor();

  const findLabel = (candidate: string, candidates: readonly FilterOption[]): string => {
    return candidates.find((opt) => opt.value === candidate)?.label ?? candidate;
  };

  const isStringArray = (value: string | readonly string[] | null): value is readonly string[] =>
    Array.isArray(value);

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

          <ComboboxContent anchor={anchor} className="pointer-events-auto">
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
            singleLabel === null && "text-muted-foreground",
          )}
        >
          <span className={cn("truncate", singleLabel === null && "text-muted-foreground")}>
            {singleLabel ?? label}
          </span>
        </ComboboxTrigger>

        <ComboboxContent className="pointer-events-auto">
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

interface ActivityPaginationProps {
  pagination: TrustedDeviceActivityPagination;
  onPageChange: (page: number) => void;
}

function ActivityPagination({ pagination, onPageChange }: ActivityPaginationProps): JSX.Element {
  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <Pagination>
      <PaginationContent>
        <PaginationItem>
          <ActivityPaginationPreviousButton onPageChange={onPageChange} pagination={pagination} />
        </PaginationItem>

        {pages.map((page, index) =>
          page === "ellipsis" ? (
            <PaginationItem key={`ellipsis-${index}`}>
              <PaginationEllipsis />
            </PaginationItem>
          ) : (
            <PaginationItem key={page}>
              <ActivityPaginationNumberButton
                onPageChange={onPageChange}
                page={page}
                pagination={pagination}
              />
            </PaginationItem>
          ),
        )}

        <PaginationItem>
          <ActivityPaginationNextButton onPageChange={onPageChange} pagination={pagination} />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  );
}

type ActivityPaginationNumberButtonProps = Pick<
  ActivityPaginationProps,
  "onPageChange" | "pagination"
> & {
  page: number;
};

function ActivityPaginationNumberButton({
  page,
  pagination,
  onPageChange,
}: ActivityPaginationNumberButtonProps): JSX.Element {
  const isActive = page === pagination.current_page;

  return (
    <Button
      aria-current={isActive ? "page" : undefined}
      onClick={() => {
        onPageChange(page);
      }}
      size="icon"
      variant={isActive ? "outline" : "ghost"}
    >
      {page}
    </Button>
  );
}

type ActivityPaginationPreviousButtonProps = Pick<
  ActivityPaginationNumberButtonProps,
  "pagination" | "onPageChange"
>;

function ActivityPaginationPreviousButton({
  pagination,
  onPageChange,
}: ActivityPaginationPreviousButtonProps): JSX.Element {
  const isDisabled = pagination.current_page <= 1;

  return (
    <Button
      aria-label="Pagina anterior"
      disabled={isDisabled}
      onClick={() => {
        onPageChange(pagination.current_page - 1);
      }}
      size="icon"
      variant="outline"
    >
      <ChevronLeft className="size-4" />
    </Button>
  );
}

type ActivityPaginationNextButtonProps = ActivityPaginationPreviousButtonProps;

function ActivityPaginationNextButton({
  pagination,
  onPageChange,
}: ActivityPaginationNextButtonProps): JSX.Element {
  const isDisabled = pagination.current_page >= pagination.last_page;

  return (
    <Button
      aria-label="Pagina siguiente"
      disabled={isDisabled}
      onClick={() => {
        onPageChange(pagination.current_page + 1);
      }}
      size="icon"
      variant="outline"
    >
      <ChevronRight className="size-4" />
    </Button>
  );
}

interface TrustedDeviceActivityDialogEventRowProps {
  event: TrustedDeviceActivityEvent;
}

function TrustedDeviceActivityDialogEventRow({
  event,
}: TrustedDeviceActivityDialogEventRowProps): JSX.Element {
  const visual = actionVisuals[event.action];
  const Icon = visual.icon;
  const colors = iconColorVariants[visual.color];

  return (
    <li className="flex items-start justify-between gap-4 p-4">
      <div className="flex items-start gap-4">
        <div className={cn("flex h-10 w-10 shrink-0 rounded-full p-2", colors.iconBgClass)}>
          <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
        </div>

        <div className="space-y-1">
          <h4 className="text-sm font-semibold">{event.deviceLabel ?? "Un dispositivo"}</h4>

          <p className="text-sm text-muted-foreground">{event.actionLabel}</p>

          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1">
              <MapPin className="h-3 w-3 text-muted-foreground" />
              <p className="text-xs text-muted-foreground">IP: {event.ip ?? "No disponible"}</p>
            </div>

            <FaCircle className="h-1 w-1" />

            <div className="flex items-center gap-1">
              {getDeviceIcon(
                { isMobile: event.deviceIsMobile ?? false, osName: event.deviceOsName },
                "h-3 w-3 text-muted-foreground",
              )}

              <p className="text-xs text-muted-foreground">
                {event.deviceOsName ?? "Sistema operativo desconocido"}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-2 text-end">
        <p className="shrink-0 text-sm font-medium text-muted-foreground">
          {fromNow(event.createdAt)}
        </p>

        <p className="shrink-0 text-sm text-muted-foreground">{formatLongDate(event.createdAt)}</p>
      </div>
    </li>
  );
}
