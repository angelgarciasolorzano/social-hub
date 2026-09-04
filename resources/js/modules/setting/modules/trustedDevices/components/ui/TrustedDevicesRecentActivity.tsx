import type { JSX } from "react";
import { Fragment, useMemo } from "react";

import { usePage } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import {
  ChevronRight,
  Pencil,
  RefreshCw,
  RotateCw,
  ShieldQuestionMark,
  Trash2,
  UserPlus,
} from "lucide-react";

import type {
  TrustedDeviceAction,
  TrustedDeviceActivityItem,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

function TrustedDevicesRecentActivity(): JSX.Element {
  const { recentActivity } = usePage<{ recentActivity: TrustedDeviceActivityItem[] }>().props;

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
        <CardTitle>Actividad reciente</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {recentActivity.length === 0 ? (
          <EmptyState
            icon={ShieldQuestionMark}
            title="Sin actividad reciente registrada."
            description="Las acciones que realizes sobre tus dispositivos apareceran aqui."
          />
        ) : (
          recentActivity.map((item) => {
            const visual = actionVisuals[item.action];
            const Icon = visual.icon;
            const colors = iconColorVariants[visual.color];

            return (
              <Fragment key={item.id}>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div className={cn("flex h-10 w-10 rounded-full p-2", colors.iconBgClass)}>
                      <Icon className={cn("h-6 w-6", colors.iconFgClass)} />
                    </div>

                    <div className="flex w-full items-center justify-between gap-4">
                      <div className="space-y-1">
                        <h4 className="max-w-40 truncate text-sm font-semibold">
                          {item.deviceLabel ?? "Un dispositivo"}
                        </h4>

                        <p className="text-sm text-muted-foreground">{item.actionLabel}</p>
                      </div>
                    </div>
                  </div>

                  <p className="text-sm text-muted-foreground">{fromNow(item.createdAt)}</p>
                </div>

                <Separator />
              </Fragment>
            );
          })
        )}
      </CardContent>
      {recentActivity.length > 0 && (
        <CardFooter className="mx-auto">
          <Button variant="link" className="text-blue-700 dark:text-blue-500">
            Ver toda la actividad
            <ChevronRight className="h-4 w-4" />
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export default TrustedDevicesRecentActivity;
