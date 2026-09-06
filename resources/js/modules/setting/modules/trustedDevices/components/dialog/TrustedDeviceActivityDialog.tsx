import type { JSX } from "react";
import { useEffect, useRef, useState } from "react";

import type { LucideIcon } from "lucide-react";
import { Pencil, RefreshCw, RotateCw, ShieldQuestionMark, Trash2, UserPlus } from "lucide-react";
import { useIntersectionObserver } from "usehooks-ts";

import { usePaginatedActivity } from "@/modules/setting/modules/trustedDevices/hooks/usePaginatedActivity";
import type {
  TrustedDeviceAction,
  TrustedDeviceActivityEvent,
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPaginated,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";

import { Button } from "@/shared/components/shadcn/ui/button";
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

const ACTION_OPTIONS: { value: string; label: string }[] = [
  { value: "", label: "Todos" },
  { value: "renamed", label: "Renombrados" },
  { value: "renewed", label: "Renovados" },
  { value: "revoked", label: "Revocados" },
  { value: "revoked_all", label: "Todos revocados" },
  { value: "reactivated", label: "Reactivados" },
];

const SINCE_OPTIONS: { value: string; label: string }[] = [
  { value: "7", label: "Últimos 7 días" },
  { value: "30", label: "Últimos 30 días" },
  { value: "90", label: "Últimos 90 días" },
  { value: "180", label: "Últimos 180 días" },
  { value: "365", label: "Todos" },
];

const actionVisuals: Record<TrustedDeviceAction, { icon: LucideIcon; color: IconColorVariant }> = {
  created: { icon: UserPlus, color: "blue" },
  renewed: { icon: RefreshCw, color: "green" },
  renamed: { icon: Pencil, color: "purple" },
  revoked: { icon: Trash2, color: "red" },
  revoked_all: { icon: Trash2, color: "red" },
  reactivated: { icon: RotateCw, color: "green" },
};

function ActionBadge({ action }: { action: TrustedDeviceAction }): JSX.Element {
  const visual = actionVisuals[action];
  const Icon = visual.icon;
  const colors = iconColorVariants[visual.color];

  return (
    <div className={cn("flex h-10 w-10 shrink-0 rounded-full p-2", colors.iconBgClass)}>
      <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
    </div>
  );
}

function EventRow({ event }: { event: TrustedDeviceActivityEvent }): JSX.Element {
  return (
    <li className="flex items-start justify-between gap-4 p-4">
      <div className="flex items-start gap-4">
        <ActionBadge action={event.action} />

        <div className="space-y-1">
          <h4 className="text-sm font-semibold">{event.deviceLabel ?? "Un dispositivo"}</h4>

          <p className="text-sm text-muted-foreground">{event.actionLabel}</p>

          {event.ip !== null && <p className="text-xs text-muted-foreground">IP: {event.ip}</p>}
        </div>
      </div>

      <p className="shrink-0 text-sm text-muted-foreground">{fromNow(event.createdAt)}</p>
    </li>
  );
}

export default function TrustedDeviceActivityDialog({
  open,
  onClose,
  initialActivity,
  initialFilters,
}: TrustedDeviceActivityDialogProps): JSX.Element {
  const { events, hasMore, isLoading, loadMore, reload } = usePaginatedActivity(initialActivity);

  const [selectedAction, setSelectedAction] = useState<string>(initialFilters.action?.[0] ?? "");
  const [selectedSince, setSelectedSince] = useState<string>(String(initialFilters.sinceDays));

  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null);
  const loadMoreTimer = useRef<number | null>(null);

  const { isIntersecting, ref: sentinelRef } = useIntersectionObserver({
    root: scrollRoot,
    rootMargin: "200px",
    threshold: 0,
  });

  const handleFilterChange = (nextAction: string, nextSince: string): void => {
    reload({ action: nextAction, sinceDays: nextSince });
  };

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
          <select
            aria-label="Filtrar por acción"
            className="rounded-md border bg-background px-3 py-1 text-sm"
            value={selectedAction}
            onChange={(event) => {
              const next = event.target.value;
              setSelectedAction(next);
              handleFilterChange(next, selectedSince);
            }}
          >
            {ACTION_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>

          <select
            aria-label="Filtrar por rango temporal"
            className="rounded-md border bg-background px-3 py-1 text-sm"
            value={selectedSince}
            onChange={(event) => {
              const next = event.target.value;
              setSelectedSince(next);
              handleFilterChange(selectedAction, next);
            }}
          >
            {SINCE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div className="max-h-[60vh] overflow-y-auto rounded-md border" ref={setScrollRoot}>
          {events.length === 0 ? (
            <div className="p-6">
              <EmptyState
                description="Prueba cambiar el rango temporal o el tipo de acción."
                icon={ShieldQuestionMark}
                title="Sin actividad para los filtros seleccionados."
              />
            </div>
          ) : (
            <ul className="divide-y">
              {events.map((event) => (
                <EventRow event={event} key={event.id} />
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
