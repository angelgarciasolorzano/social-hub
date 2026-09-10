import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";

import {
  ChevronLeft,
  ChevronRight,
  Clock,
  Info,
  MapPin,
  Pencil,
  RefreshCw,
  RotateCw,
  Search,
  ShieldQuestionMark,
  Trash2,
  UserPlus,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceActivityFilters";
import { useActivityFilters } from "@/modules/setting/modules/trustedDevices/hooks/useActivityFilters";
import type {
  TrustedDeviceAction,
  TrustedDeviceActivityEvent,
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPagination,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { computePaginationRange } from "@/modules/setting/modules/trustedDevices/utils/pagination";
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
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceActivityDialogProps {
  open: boolean;
  onClose: () => void;
  initialActivity: TrustedDeviceActivityPagination;
  initialFilters: TrustedDeviceActivityFilters;
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
  initialActivity,
  initialFilters,
}: TrustedDeviceActivityDialogProps): JSX.Element {
  const { filters, goToPage, updateFilter } = useActivityFilters(initialFilters);

  const filterConfigs: readonly FilterComboboxConfig[] = [
    {
      className: "w-56",
      label: "Acción",
      multiple: true,
      options: activityActionOptions,
      value: filters.action,
      onChange: (value) => {
        if (value === null) {
          updateFilter("action", []);
        } else if (typeof value === "string") {
          updateFilter("action", [value as TrustedDeviceActivityActionFilter]);
        } else {
          updateFilter("action", [...value] as TrustedDeviceActivityActionFilter[]);
        }
      },
    },
    {
      className: "w-48",
      label: "Filtrar por rango temporal",
      multiple: false,
      options: activitySinceDaysOptions,
      value: filters.sinceDays,
      onChange: (value) => {
        if (value === null) return;
        updateFilter("sinceDays", value as TrustedDeviceActivitySinceDaysFilter);
      },
    },
  ];

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

        <div className="flex items-start gap-2">
          <div className="flex min-w-0 flex-1 flex-col gap-1.5">
            <label className="text-xs font-medium text-muted-foreground select-none">Buscar</label>

            <InputGroup>
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
            </InputGroup>
          </div>

          <div className="flex shrink-0 gap-2">
            {filterConfigs.map((config) => (
              <ActivityFilterCombobox
                key={config.label}
                className={config.className}
                label={config.label}
                multiple={config.multiple}
                onChange={config.onChange}
                options={config.options}
                value={config.value}
              />
            ))}
          </div>
        </div>

        <div className="max-h-[60vh] rounded-xl border">
          {initialActivity.data.length === 0 ? (
            <div className="p-6">
              <EmptyState
                description="Prueba cambiar el rango temporal, el tipo de acción o el termino de busqueda."
                icon={ShieldQuestionMark}
                title="Sin actividad para los filtros seleccionados."
              />
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
      </DialogContent>
    </Dialog>
  );
}

interface FilterOption {
  readonly label: string;
  readonly value: string;
}

interface FilterComboboxConfig {
  className?: string;
  label: string;
  multiple: boolean;
  options: readonly FilterOption[];
  value: string | readonly string[] | null;
  onChange: (value: string | readonly string[] | null) => void;
}

function ActivityFilterCombobox(props: FilterComboboxConfig): JSX.Element {
  const { className, label, multiple, options, value, onChange } = props;

  const anchor = useComboboxAnchor();

  const findLabel = (candidate: string, candidates: readonly FilterOption[]): string => {
    return candidates.find((opt) => opt.value === candidate)?.label ?? candidate;
  };

  const isStringArray = (value: string | readonly string[] | null): value is readonly string[] =>
    Array.isArray(value);

  if (multiple) {
    const arr: string[] = isStringArray(value) ? [...value] : value === null ? [] : [value];

    return (
      <div className={cn("flex min-w-0 shrink-0 flex-col gap-1.5", className)}>
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
    <div className={cn("flex min-w-0 shrink-0 flex-col gap-1.5", className)}>
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
