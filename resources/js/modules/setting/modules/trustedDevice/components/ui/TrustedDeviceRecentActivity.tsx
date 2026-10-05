import type { JSX } from "react";
import { useMemo } from "react";

import { usePage } from "@inertiajs/react";

import {
  ChevronRight,
  Pencil,
  RefreshCw,
  RotateCw,
  ShieldQuestionMark,
  Trash2,
  UserPlus,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type {
  TrustedDeviceAction,
  TrustedDeviceActivityItem,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/shared/components/shadcn/ui/card";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

interface TrustedDeviceRecentActivityProps {
  onOpenActivity: () => void;
}

interface TrustedDeviceRecentActivityPageProps extends SharedData {
  recentActivity: TrustedDeviceActivityItem[];
}

function TrustedDeviceRecentActivity({
  onOpenActivity,
}: TrustedDeviceRecentActivityProps): JSX.Element {
  const { recentActivity } = usePage<TrustedDeviceRecentActivityPageProps>().props;
  const actionVisuals = useMemo<
    Record<TrustedDeviceAction, { icon: LucideIcon; color: IconColorVariant }>
  >(
    () => ({
      created: { icon: UserPlus, color: "blue" },
      renewed: { icon: RefreshCw, color: "green" },
      renamed: { icon: Pencil, color: "purple" },
      revoked: { icon: Trash2, color: "red" },
      revoked_all: { icon: Trash2, color: "red" },
      reactivated: { icon: RotateCw, color: "green" },
    }),
    [],
  );

  return (
    <Card>
      <CardHeader>
        <h2 className="leading-none font-semibold tracking-tight">Actividad reciente</h2>
      </CardHeader>
      <CardContent className="space-y-4">
        {recentActivity.length === 0 ? (
          <EmptyState
            description="Las acciones que realices sobre tus dispositivos aparecerán aquí."
            icon={ShieldQuestionMark}
            title="Sin actividad reciente registrada."
          />
        ) : (
          <ul className="space-y-4" role="list">
            {recentActivity.map((item) => {
              const visual = actionVisuals[item.action];
              const Icon = visual.icon;
              const colors = iconColorVariants[visual.color];

              return (
                <li className="space-y-4" key={item.id}>
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-start gap-4">
                      <div
                        aria-hidden="true"
                        className={cn("flex h-10 w-10 rounded-full p-2", colors.iconBgClass)}
                      >
                        <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
                      </div>

                      <div className="flex w-full items-center justify-between gap-4">
                        <div className="space-y-1">
                          <h3 className="max-w-40 truncate text-sm font-semibold">
                            {item.deviceLabel ?? "Un dispositivo"}
                          </h3>

                          <p className="text-sm text-muted-foreground">{item.actionLabel}</p>
                        </div>
                      </div>
                    </div>

                    <time
                      className="text-sm text-muted-foreground"
                      dateTime={item.createdAt ?? undefined}
                    >
                      {fromNow(item.createdAt)}
                    </time>
                  </div>

                  <Separator />
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>

      {recentActivity.length > 0 && (
        <CardFooter className="mx-auto">
          <Button
            className="cursor-pointer text-blue-700 dark:text-blue-500"
            onClick={onOpenActivity}
            variant="link"
          >
            Ver toda la actividad
            <ChevronRight className="h-4 w-4" />
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export default TrustedDeviceRecentActivity;
