import type { JSX } from "react";
import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { FaCircle } from "react-icons/fa";

import {
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
import type {
  TrustedDeviceAction,
  TrustedDeviceActivityEvent,
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPagination,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import {
  buildPageUrl,
  computePaginationRange,
} from "@/modules/setting/modules/trustedDevices/utils/pagination";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
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
  const [selectedActions, setSelectedActions] = useState<TrustedDeviceActivityActionFilter[]>(
    initialFilters.action ?? [],
  );
  const [selectedSince, setSelectedSince] = useState<TrustedDeviceActivitySinceDaysFilter>(
    initialFilters.sinceDays,
  );
  const [searchQuery, setSearchQuery] = useState<string>(initialFilters.search);
  const isFirstSearchRender = useRef<boolean>(true);

  const navigate = useCallback(
    (overrides: {
      action?: TrustedDeviceActivityActionFilter[] | null;
      sinceDays?: TrustedDeviceActivitySinceDaysFilter;
      search?: string;
    }): void => {
      const actionValue =
        overrides.action !== undefined
          ? overrides.action === null
            ? undefined
            : overrides.action.join(",")
          : selectedActions.length === 0
            ? undefined
            : selectedActions.join(",");

      router.get(
        index().url,
        {
          action: actionValue,
          since_days: overrides.sinceDays ?? selectedSince,
          search: overrides.search ?? searchQuery,
        },
        {
          only: ["activityLog"],
          preserveState: true,
          preserveUrl: true,
        },
      );
    },
    [selectedActions, selectedSince, searchQuery],
  );

  // Debounced search: reload on typing, but skip the first render to avoid
  // an immediate reload when the dialog opens with a non-empty initial search.
  useEffect(() => {
    if (isFirstSearchRender.current) {
      isFirstSearchRender.current = false;
      return;
    }
    const timer = window.setTimeout(() => {
      navigate({ search: searchQuery });
    }, 500);
    return () => {
      window.clearTimeout(timer);
    };
  }, [searchQuery, navigate]);

  const filterConfigs: readonly FilterComboboxConfig[] = [
    {
      label: "Acción",
      multiple: true,
      options: activityActionOptions,
      value: selectedActions,
      onChange: (value) => {
        if (value === null) {
          setSelectedActions([]);
          navigate({ action: null });
        } else if (typeof value === "string") {
          const coerced = [value as TrustedDeviceActivityActionFilter];
          setSelectedActions(coerced);
          navigate({ action: coerced });
        } else {
          const coerced = [...value] as TrustedDeviceActivityActionFilter[];
          setSelectedActions(coerced);
          navigate({ action: coerced });
        }
      },
    },
    {
      label: "Filtrar por rango temporal",
      multiple: false,
      options: activitySinceDaysOptions,
      value: selectedSince,
      onChange: (value) => {
        if (value === null) return;
        const coerced = value as TrustedDeviceActivitySinceDaysFilter;
        setSelectedSince(coerced);
        navigate({ sinceDays: coerced });
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
          <DialogTitle>Actividad reciente de dispositivos</DialogTitle>
          <DialogDescription>
            Historial de eventos relacionados con tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        <div className="flex items-center gap-2">
          <InputGroup className="flex-1">
            <InputGroupAddon>
              <Search />
            </InputGroupAddon>
            <InputGroupInput
              onChange={(event) => {
                setSearchQuery(event.target.value);
              }}
              placeholder="Buscar por dispositivo, ubicación o IP..."
              value={searchQuery}
            />
          </InputGroup>

          <div className="flex shrink-0 gap-2">
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
          </div>
        </div>

        <div className="max-h-[60vh]">
          {initialActivity.data.length === 0 ? (
            <div className="p-6">
              <EmptyState
                description="Prueba cambiar el rango temporal, el tipo de acción o el termino de busqueda."
                icon={ShieldQuestionMark}
                title="Sin actividad para los filtros seleccionados."
              />
            </div>
          ) : (
            <ul>
              {initialActivity.data.map((event) => (
                <TrustedDeviceActivityDialogEventRow event={event} key={event.id} />
              ))}
            </ul>
          )}
        </div>

        <DialogFooter className="flex items-center justify-between sm:justify-between">
          <span className="text-xs text-muted-foreground">
            {initialActivity.total === 0
              ? "Sin eventos"
              : `Mostrando ${initialActivity.from ?? 0}-${initialActivity.to ?? 0} de ${initialActivity.total} eventos`}
          </span>

          <div className="flex items-center gap-3">
            {initialActivity.last_page > 1 && <ActivityPagination pagination={initialActivity} />}

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
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
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
    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
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
}

function ActivityPagination({ pagination }: ActivityPaginationProps): JSX.Element {
  const range = computePaginationRange(pagination.current_page, pagination.last_page);

  const previousUrl = pagination.prev_page_url;
  const nextUrl = pagination.next_page_url;

  return (
    <Pagination>
      <PaginationContent>
        <PaginationItem>
          <PaginationPrevious
            aria-label="Ir a la pagina anterior"
            className={cn(previousUrl === null && "pointer-events-none opacity-50")}
            href={previousUrl ?? "#"}
          />
        </PaginationItem>

        {range.map((entry, index) =>
          entry === "ellipsis" ? (
            <PaginationItem key={`ellipsis-${index}`}>
              <PaginationEllipsis />
            </PaginationItem>
          ) : (
            <PaginationItem key={entry}>
              <PaginationLink
                href={buildPageUrl(pagination, entry) ?? "#"}
                isActive={entry === pagination.current_page}
              >
                {entry}
              </PaginationLink>
            </PaginationItem>
          ),
        )}

        <PaginationItem>
          <PaginationNext
            aria-label="Ir a la pagina siguiente"
            className={cn(nextUrl === null && "pointer-events-none opacity-50")}
            href={nextUrl ?? "#"}
          />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
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
