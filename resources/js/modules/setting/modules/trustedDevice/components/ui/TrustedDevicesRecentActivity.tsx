import type { JSX } from "react";
import { Fragment, useMemo } from "react";

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

import TrustedDeviceActivityDialog from "@/modules/setting/modules/trustedDevice/components/dialog/TrustedDeviceActivityDialog";
import type {
  TrustedDeviceAction,
  TrustedDeviceActivityItem,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

interface ActivityDialogState extends DialogClosingState {
  kind: "open";
}

interface TrustedDevicesRecentActivityPageProps extends SharedData {
  recentActivity: TrustedDeviceActivityItem[];
}

function TrustedDevicesRecentActivity(): JSX.Element {
  const { recentActivity } = usePage<TrustedDevicesRecentActivityPageProps>().props;

  const activityDialog = useDialog<ActivityDialogState | null>(null);
  const handleActivityClose = createDialogCloseHandler(activityDialog);

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
    <>
      <Card>
        <CardHeader>
          <CardTitle>Actividad reciente</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {recentActivity.length === 0 ? (
            <EmptyState
              description="Las acciones que realizes sobre tus dispositivos apareceran aqui."
              icon={ShieldQuestionMark}
              title="Sin actividad reciente registrada."
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
            <Button
              className="cursor-pointer text-blue-700 dark:text-blue-500"
              onClick={() => {
                activityDialog.show({ kind: "open", closing: false });
              }}
              variant="link"
            >
              Ver toda la actividad
              <ChevronRight className="h-4 w-4" />
            </Button>
          </CardFooter>
        )}
      </Card>

      {activityDialog.state !== null && (
        <TrustedDeviceActivityDialog
          onClose={handleActivityClose}
          open={!activityDialog.state.closing}
        />
      )}
    </>
  );
}

export default TrustedDevicesRecentActivity;
