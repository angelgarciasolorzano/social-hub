import type { JSX } from "react";

import { FaCircle } from "react-icons/fa";

import {
  Inbox,
  MapPin,
  Pencil,
  RefreshCw,
  RotateCw,
  SearchX,
  Trash2,
  UserPlus,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { TrustedDeviceAction } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type { TrustedDeviceActivityEvent } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { formatLongDate, fromNow } from "@/modules/setting/shared/utils/dateTime";
import { getDeviceIcon } from "@/modules/setting/shared/utils/trustedDevice";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

const actionVisuals: Record<TrustedDeviceAction, { icon: LucideIcon; color: IconColorVariant }> = {
  created: { icon: UserPlus, color: "blue" },
  renewed: { icon: RefreshCw, color: "green" },
  renamed: { icon: Pencil, color: "purple" },
  revoked: { icon: Trash2, color: "red" },
  revoked_all: { icon: Trash2, color: "red" },
  reactivated: { icon: RotateCw, color: "green" },
};

export interface TrustedDeviceActivityEventListProps {
  events: TrustedDeviceActivityEvent[];
  hasActiveFilters: boolean;
}

export default function TrustedDeviceActivityEventList({
  events,
  hasActiveFilters,
}: TrustedDeviceActivityEventListProps): JSX.Element {
  return (
    <div className="max-h-[60vh] rounded-xl border dark:bg-muted/20">
      {events.length === 0 ? (
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
          {events.map((event) => (
            <ActivityEventRow event={event} key={event.id} />
          ))}
        </ul>
      )}
    </div>
  );
}

interface ActivityEventRowProps {
  event: TrustedDeviceActivityEvent;
}

function ActivityEventRow({ event }: ActivityEventRowProps): JSX.Element {
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
