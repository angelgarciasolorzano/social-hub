import { type JSX, useRef } from "react";

import { usePage } from "@inertiajs/react";

import {
  ArrowRight,
  Circle,
  CircleAlert,
  Clock,
  Laptop,
  type LucideIcon,
  Monitor,
  ShieldCheck,
  Smartphone,
  Trash2,
} from "lucide-react";
import { LabelList, RadialBar, RadialBarChart } from "recharts";

import type { TrustedDeviceStats } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardDescription, CardHeader } from "@/shared/components/shadcn/ui/card";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/shared/components/shadcn/ui/chart";
import type { ChartConfig } from "@/shared/components/shadcn/ui/chart";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import { Progress } from "@/shared/components/shadcn/ui/progress";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import {
  alertVariants,
  type IconColorVariant,
  iconColorVariants,
  progressBarClassesByVariant,
} from "@/shared/lib/styling";

import TrustedDeviceRecommendationsDialog from "./TrustedDeviceRecommendationsDialog";

function percentageOf(value: number, total: number): number {
  if (total <= 0) {
    return 0;
  }

  return Math.round((value / total) * 100);
}

interface TrustedDeviceSummaryDialogProps {
  open: boolean;
  onClose: () => void;
}

function TrustedDeviceSummaryDialog({
  open,
  onClose,
}: TrustedDeviceSummaryDialogProps): JSX.Element {
  const returnFocusRef = useRef<HTMLElement | null>(null);
  const { stats } = usePage<{ stats: TrustedDeviceStats }>().props;

  const recommendationsDialog = useDialog<DialogClosingState | null>(null);
  const handleRecommendationsClose = createDialogCloseHandler(recommendationsDialog);

  return (
    <>
      <Dialog
        open={open}
        onOpenChange={(next) => {
          if (!next) onClose();
        }}
      >
        <DialogContent
          className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-4xl"
          onOpenAutoFocus={() => {
            const activeElement = document.activeElement;

            returnFocusRef.current =
              activeElement instanceof HTMLElement && activeElement !== document.body
                ? activeElement
                : null;
          }}
          onCloseAutoFocus={(event) => {
            const focusTarget = returnFocusRef.current;

            if (focusTarget?.isConnected) {
              event.preventDefault();
              focusTarget.focus();
            }

            returnFocusRef.current = null;
          }}
        >
          <DialogHeader>
            <DialogTitle asChild>
              <h2 className="flex items-center gap-2">
                <ShieldCheck aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
                Resumen de dispositivos
              </h2>
            </DialogTitle>

            <DialogDescription>
              Aquí puedes ver el detalle de la distribución y estado de tus dispositivos de
              confianza.
            </DialogDescription>
          </DialogHeader>

          <ul className="grid list-none grid-cols-2 gap-3 md:grid-cols-4" role="list">
            <StatCard trustedDeviceStats={stats} />
          </ul>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StateDistributionBreakdown trustedDeviceStats={stats} />

            <DeviceTypeBreakdown trustedDeviceStats={stats} />
          </div>

          <Alert className={alertVariants.preview}>
            <CircleAlert />
            <AlertTitle>Mantén tus dispositivos seguros</AlertTitle>
            <AlertDescription className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
              Revisa periódicamente los dispositivos que ya no utilizas y elimina aquellos que no
              reconozcas. Esto ayuda a proteger tu cuenta y evitar accesos no autorizados.
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  recommendationsDialog.show({ closing: false });
                }}
              >
                Ver recomendaciones
                <ArrowRight />
              </Button>
            </AlertDescription>
          </Alert>

          <DialogFooter>
            <DialogClose asChild>
              <Button variant="outline">Cerrar</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {recommendationsDialog.state !== null && (
        <TrustedDeviceRecommendationsDialog
          open={!recommendationsDialog.state.closing}
          onClose={handleRecommendationsClose}
        />
      )}
    </>
  );
}

interface StatCardProps {
  trustedDeviceStats: TrustedDeviceStats;
}

interface StatCardRow {
  icon: LucideIcon;
  label: string;
  total: number;
  value: number;
  variant: IconColorVariant;
}

function StatCard({ trustedDeviceStats }: StatCardProps): JSX.Element {
  const statCardRows: StatCardRow[] = [
    {
      icon: Monitor,
      label: "Activos",
      total: trustedDeviceStats.total,
      value: trustedDeviceStats.active,
      variant: "green",
    },
    {
      icon: Clock,
      label: "Próximos a expirar",
      total: trustedDeviceStats.total,
      value: trustedDeviceStats.expiringSoon,
      variant: "amber",
    },
    {
      icon: Monitor,
      label: "Inactivos",
      total: trustedDeviceStats.total,
      value: trustedDeviceStats.inactive,
      variant: "gray",
    },
    {
      icon: Trash2,
      label: "Revocados",
      total: trustedDeviceStats.total,
      value: trustedDeviceStats.revoked,
      variant: "red",
    },
  ];

  return (
    <>
      {statCardRows.map((item) => {
        const Icon = item.icon;
        const percentage = percentageOf(item.value, item.total);

        return (
          <li
            className="flex min-w-0 items-center gap-3 rounded-xl border bg-card p-3 shadow-sm dark:bg-muted/20"
            key={item.label}
          >
            <div
              aria-hidden="true"
              className={cn(
                "flex h-10 w-10 shrink-0 rounded-full p-2",
                iconColorVariants[item.variant].iconBgClass,
              )}
            >
              <Icon className={cn("h-6 w-6", iconColorVariants[item.variant].iconFgClass)} />
            </div>

            <dl className="flex min-w-0 flex-col">
              <dt className="text-xs text-muted-foreground">{item.label}</dt>
              <dd className="text-xl font-semibold">{item.value}</dd>
              <dd className="text-xs text-muted-foreground">{percentage}% del total</dd>
            </dl>
          </li>
        );
      })}
    </>
  );
}

interface DeviceTypeRow {
  count: number;
  icon: LucideIcon;
  label: string;
  variant: IconColorVariant;
}

type DeviceTypeBreakdownProps = StatCardProps;

function DeviceTypeBreakdown({ trustedDeviceStats }: DeviceTypeBreakdownProps): JSX.Element {
  const deviceTypeRows: DeviceTypeRow[] = [
    {
      count: trustedDeviceStats.byDeviceType.desktop,
      icon: Laptop,
      label: "Escritorio / Laptop",
      variant: "violet",
    },
    {
      count: trustedDeviceStats.byDeviceType.mobile,
      icon: Smartphone,
      label: "Móvil / Tablet",
      variant: "green",
    },
  ];

  const totalByType = deviceTypeRows.reduce((sum, row) => sum + row.count, 0);

  return (
    <Card className="dark:bg-muted/20">
      <CardHeader>
        <h3 className="leading-none font-semibold" id="device-type-breakdown-title">
          Dispositivo por tipo
        </h3>
        <CardDescription>Cantidad de dispositivos por categoría</CardDescription>
      </CardHeader>

      <CardContent>
        <ul
          aria-labelledby="device-type-breakdown-title"
          className="flex-1 list-none space-y-4"
          role="list"
        >
          {deviceTypeRows.map((item) => {
            const percentage = percentageOf(item.count, totalByType);
            const Icon = item.icon;

            return (
              <li className="space-y-1.5" key={item.label}>
                <div className="flex items-center gap-3">
                  <div
                    aria-hidden="true"
                    className={cn(
                      "flex h-10 w-10 shrink-0 rounded-md p-2",
                      iconColorVariants[item.variant].iconBgClass,
                    )}
                  >
                    <Icon className={cn("h-6 w-6", iconColorVariants[item.variant].iconFgClass)} />
                  </div>

                  <dl className="flex flex-1 items-center gap-3">
                    <dt className="flex-1 text-sm font-medium">{item.label}</dt>
                    <dd className="text-sm font-semibold">{item.count}</dd>
                    <dd className="w-10 text-right text-xs text-muted-foreground">{percentage}%</dd>
                  </dl>
                </div>

                <Progress
                  className={cn("h-1.5", progressBarClassesByVariant[item.variant])}
                  value={percentage}
                />
              </li>
            );
          })}

          {totalByType === 0 && (
            <li className="text-sm text-muted-foreground">Aún no tienes dispositivos activos.</li>
          )}
        </ul>
      </CardContent>
    </Card>
  );
}

type StateDistributionBreakdownProps = StatCardProps;

interface CharData {
  cantidad: number;
  estado: "activos" | "inactivos" | "porExpirar" | "revocados";
  fill: string;
  label: string;
  variant: IconColorVariant;
}

function StateDistributionBreakdown({
  trustedDeviceStats,
}: StateDistributionBreakdownProps): JSX.Element {
  const activeOnlyCount = Math.max(0, trustedDeviceStats.active - trustedDeviceStats.expiringSoon);

  const chartConfig = {
    cantidad: { label: "Dispositivos" },
    activos: {
      label: "Activos",
      theme: {
        light: "oklch(0.723 0.219 149.579)",
        dark: "oklch(0.792 0.209 151.711)",
      },
    },
    porExpirar: {
      label: "Próximos a expirar",
      theme: {
        light: "oklch(0.769 0.188 70.08)",
        dark: "oklch(0.828 0.189 84.429)",
      },
    },
    inactivos: {
      label: "Inactivos",
      theme: {
        light: "oklch(0.551 0.027 264.364)",
        dark: "oklch(0.707 0.022 261.325)",
      },
    },
    revocados: {
      label: "Revocados",
      theme: {
        light: "oklch(0.637 0.237 25.331)",
        dark: "oklch(0.704 0.191 22.216)",
      },
    },
  } satisfies ChartConfig;

  const chartData: CharData[] = [
    {
      cantidad: activeOnlyCount,
      estado: "activos",
      fill: "var(--color-activos)",
      label: "Activos",
      variant: "green",
    },
    {
      cantidad: trustedDeviceStats.expiringSoon,
      estado: "porExpirar",
      fill: "var(--color-porExpirar)",
      label: "Próximos a expirar",
      variant: "amber",
    },
    {
      cantidad: trustedDeviceStats.inactive,
      estado: "inactivos",
      fill: "var(--color-inactivos)",
      label: "Inactivos",
      variant: "gray",
    },
    {
      cantidad: trustedDeviceStats.revoked,
      estado: "revocados",
      fill: "var(--color-revocados)",
      label: "Revocados",
      variant: "red",
    },
  ];

  return (
    <Card className="dark:bg-muted/20">
      <CardHeader>
        <h3 className="leading-none font-semibold" id="device-state-distribution-title">
          Distribución por estado
        </h3>
        <CardDescription>Porcentaje del total de dispositivos</CardDescription>
      </CardHeader>

      <CardContent className="flex flex-col items-center gap-4 sm:flex-row">
        <ChartContainer config={chartConfig} className="aspect-square max-h-44 w-44 shrink-0">
          <RadialBarChart
            data={chartData}
            cy="50%"
            cx="50%"
            endAngle={380}
            innerRadius="40%"
            outerRadius="100%"
            startAngle={-90}
          >
            <ChartTooltip
              content={<ChartTooltipContent hideLabel nameKey="estado" />}
              cursor={false}
            />

            <RadialBar background dataKey="cantidad">
              <LabelList
                className="fill-white capitalize mix-blend-luminosity"
                dataKey="label"
                fontSize={11}
                position="insideStart"
              />
            </RadialBar>
          </RadialBarChart>
        </ChartContainer>

        <ul
          aria-labelledby="device-state-distribution-title"
          className="flex min-w-0 flex-1 flex-col gap-2"
          role="list"
        >
          {chartData.map((item) => {
            const percentage = percentageOf(item.cantidad, trustedDeviceStats.total);

            return (
              <li className="flex items-center justify-between gap-2" key={item.estado}>
                <div className="flex min-w-0 items-center gap-2">
                  <Circle
                    aria-hidden="true"
                    className={cn("h-2 w-2 shrink-0", iconColorVariants[item.variant].iconFgClass)}
                    fill="currentColor"
                  />

                  <span className="truncate text-sm">{item.label}</span>
                </div>

                <div className="flex items-center gap-4">
                  <span className="text-sm font-semibold">{item.cantidad}</span>

                  <span className="text-xs text-muted-foreground">{percentage}%</span>
                </div>
              </li>
            );
          })}
        </ul>
      </CardContent>
    </Card>
  );
}

export default TrustedDeviceSummaryDialog;
