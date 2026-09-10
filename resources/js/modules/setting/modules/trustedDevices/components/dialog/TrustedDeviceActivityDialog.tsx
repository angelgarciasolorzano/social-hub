import type { JSX } from "react";
import { useEffect, useRef, useState } from "react";

import { FaCircle } from "react-icons/fa";

import type { LucideIcon } from "lucide-react";
import {
  MapPin,
  Pencil,
  RefreshCw,
  RotateCw,
  ShieldQuestionMark,
  Trash2,
  UserPlus,
} from "lucide-react";
import { useIntersectionObserver } from "usehooks-ts";

import {
  activityActionOptions,
  activitySinceDaysOptions,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceActivityFilters";
import { usePaginatedActivity } from "@/modules/setting/modules/trustedDevices/hooks/usePaginatedActivity";
import type {
  TrustedDeviceAction,
  TrustedDeviceActivityEvent,
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPaginated,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
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

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceActivityDialogProps {
  open: boolean;
  onClose: () => void;
  initialActivity: TrustedDeviceActivityPaginated;
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
  const { events, hasMore, isLoading, loadMore, reload } = usePaginatedActivity(initialActivity);

  const [selectedActions, setSelectedActions] = useState<TrustedDeviceActivityActionFilter[]>(
    initialFilters.action ?? [],
  );
  const [selectedSince, setSelectedSince] = useState<TrustedDeviceActivitySinceDaysFilter>(
    initialFilters.sinceDays,
  );

  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    root: scrollRoot,
    rootMargin: "200px",
    threshold: 0,
  });

  const triggerReload = (
    nextActions: TrustedDeviceActivityActionFilter[] | TrustedDeviceActivityActionFilter | null,
    nextSince: TrustedDeviceActivitySinceDaysFilter,
  ): void => {
    const actionsArray: TrustedDeviceActivityActionFilter[] | null = Array.isArray(nextActions)
      ? Array.from(nextActions)
      : nextActions === null
        ? null
        : [nextActions];

    reload({
      action: actionsArray,
      sinceDays: nextSince,
    });
  };

  const filterConfigs: readonly FilterComboboxConfig[] = [
    {
      label: "Acción",
      multiple: true,
      options: activityActionOptions,
      value: selectedActions,
      onChange: (value) => {
        if (value === null) {
          setSelectedActions([]);
          triggerReload(null, selectedSince);
        } else if (typeof value === "string") {
          const coerced = [value as TrustedDeviceActivityActionFilter];
          setSelectedActions(coerced);
          triggerReload(coerced, selectedSince);
        } else {
          const coerced = [...value] as TrustedDeviceActivityActionFilter[];
          setSelectedActions(coerced);
          triggerReload(coerced, selectedSince);
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
        triggerReload(selectedActions, coerced);
      },
    },
  ];

  useEffect(() => {
    if (!isIntersecting || isLoading || !hasMore) return;
    if (loadMoreTimer.current !== null) return;

    loadMoreTimer.current = window.setTimeout(() => {
      loadMoreTimer.current = null;
      loadMore();
    }, 300);

    return () => {
      if (loadMoreTimer.current !== null) {
        window.clearTimeout(loadMoreTimer.current);
        loadMoreTimer.current = null;
      }
    };
  }, [loadMore, isIntersecting, isLoading, hasMore]);

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

        <div className="flex gap-2">
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

        <div className="max-h-[60vh] overflow-y-auto" ref={setScrollRoot}>
          {events.length === 0 ? (
            <div className="p-6">
              <EmptyState
                description="Prueba cambiar el rango temporal o el tipo de acción."
                icon={ShieldQuestionMark}
                title="Sin actividad para los filtros seleccionados."
              />
            </div>
          ) : (
            <ul className="space-y-4 py-2">
              {events.map((event) => (
                <TrustedDeviceActivityDialogEventRow event={event} key={event.id} />
              ))}
            </ul>
          )}

          <div className="w-full" ref={sentinelRef} />

          {hasMore && (
            <p className="py-2 text-center text-sm text-muted-foreground">
              {isLoading ? "Cargando..." : "Desliza para cargar más..."}
            </p>
          )}
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
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
    <li className="flex items-start justify-between gap-4 rounded-xl border p-4 shadow-sm">
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
