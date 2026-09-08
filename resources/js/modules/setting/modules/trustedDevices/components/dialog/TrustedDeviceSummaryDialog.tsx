import type { JSX } from "react";

import { usePage } from "@inertiajs/react";

import {
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

import type { TrustedDeviceStats } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
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

import { cn } from "@/shared/lib";
import { alertVariants, type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceSummaryDialogProps {
  open: boolean;
  onClose: () => void;
}

interface StatCardProps {
  icon: LucideIcon;
  label: string;
  total: number;
  value: number;
  variant: IconColorVariant;
}

interface DeviceTypeRow {
  count: number;
  icon: LucideIcon;
  label: string;
  variant: IconColorVariant;
}

interface TrustedDeviceSummaryDatum {
  cantidad: number;
  estado: "activos" | "inactivos" | "porExpirar" | "revocados";
  fill: string;
  label: string;
}

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

const colorVariantForEstado: Record<TrustedDeviceSummaryDatum["estado"], IconColorVariant> = {
  activos: "green",
  inactivos: "gray",
  porExpirar: "amber",
  revocados: "red",
};

function percentageOf(value: number, total: number): number {
  if (total <= 0) {
    return 0;
  }

  return Math.round((value / total) * 100);
}

function solidBarClass(variant: IconColorVariant): string {
  // iconBgClass uses /50 (light) / /20 (dark) opacity — strip it for the progress bar fill
  const colors = iconColorVariants[variant];

  return cn(
    colors.iconBgClass.replace("/50", "").replace("/20", ""),
    colors.iconFgClass.replace("-100", "-500").replace("-900", "-800"),
  );
}

const progressBarClassesByVariant = {
  amber: cn(
    "bg-amber-200 dark:bg-amber-800",
    "**:data-[slot=progress-indicator]:bg-amber-700 dark:**:data-[slot=progress-indicator]:bg-amber-400",
  ),
  blue: cn(
    "bg-blue-200 dark:bg-blue-800",
    "**:data-[slot=progress-indicator]:bg-blue-700 dark:**:data-[slot=progress-indicator]:bg-blue-400",
  ),
  cyan: cn(
    "bg-cyan-200 dark:bg-cyan-800",
    "**:data-[slot=progress-indicator]:bg-cyan-700 dark:**:data-[slot=progress-indicator]:bg-cyan-400",
  ),
  gray: cn(
    "bg-gray-200 dark:bg-gray-800",
    "**:data-[slot=progress-indicator]:bg-gray-700 dark:**:data-[slot=progress-indicator]:bg-gray-400",
  ),
  green: cn(
    "bg-green-200 dark:bg-green-800",
    "**:data-[slot=progress-indicator]:bg-green-700 dark:**:data-[slot=progress-indicator]:bg-green-400",
  ),
  orange: cn(
    "bg-orange-200 dark:bg-orange-800",
    "**:data-[slot=progress-indicator]:bg-orange-700 dark:**:data-[slot=progress-indicator]:bg-orange-400",
  ),
  purple: cn(
    "bg-purple-200 dark:bg-purple-800",
    "**:data-[slot=progress-indicator]:bg-purple-700 dark:**:data-[slot=progress-indicator]:bg-purple-400",
  ),
  red: cn(
    "bg-red-200 dark:bg-red-800",
    "**:data-[slot=progress-indicator]:bg-red-700 dark:**:data-[slot=progress-indicator]:bg-red-400",
  ),
  violet: cn(
    "bg-violet-200 dark:bg-violet-800",
    "**:data-[slot=progress-indicator]:bg-violet-700 dark:**:data-[slot=progress-indicator]:bg-violet-400",
  ),
  yellow: cn(
    "bg-yellow-200 dark:bg-yellow-800",
    "**:data-[slot=progress-indicator]:bg-yellow-700 dark:**:data-[slot=progress-indicator]:bg-yellow-400",
  ),
} as const satisfies Record<IconColorVariant, string>;

function StatCard({ icon: Icon, label, total, value, variant }: StatCardProps): JSX.Element {
  const percentage = percentageOf(value, total);

  return (
    <div className="flex items-center gap-3 rounded-lg border p-3">
      <div
        className={cn(
          "flex h-10 w-10 shrink-0 rounded-full p-2",
          iconColorVariants[variant].iconBgClass,
        )}
      >
        <Icon className={cn("h-6 w-6", iconColorVariants[variant].iconFgClass)} />
      </div>

      <div className="flex flex-col">
        <span className="text-xs text-muted-foreground">{label}</span>
        <span className="text-xl font-semibold">{value}</span>
        <span className="text-xs text-muted-foreground">{percentage}% del total</span>
      </div>
    </div>
  );
}

function TrustedDeviceSummaryDialog({
  open,
  onClose,
}: TrustedDeviceSummaryDialogProps): JSX.Element {
  const { stats } = usePage<{ stats: TrustedDeviceStats }>().props;

  const total = stats.total;
  const activeOnlyCount = Math.max(0, stats.active - stats.expiringSoon);

  const chartData: TrustedDeviceSummaryDatum[] = [
    {
      cantidad: activeOnlyCount,
      estado: "activos",
      fill: "var(--color-activos)",
      label: "Activos",
    },
    {
      cantidad: stats.expiringSoon,
      estado: "porExpirar",
      fill: "var(--color-por-expirar)",
      label: "Próximos a expirar",
    },
    {
      cantidad: stats.inactive,
      estado: "inactivos",
      fill: "var(--color-inactivos)",
      label: "Inactivos",
    },
    {
      cantidad: stats.revoked,
      estado: "revocados",
      fill: "var(--color-revocados)",
      label: "Revocados",
    },
  ];

  const desktopCount = stats.byDeviceType.desktop;
  const mobileCount = stats.byDeviceType.mobile;
  const totalByType = desktopCount + mobileCount;

  const deviceTypeRows: DeviceTypeRow[] = [
    { count: desktopCount, icon: Laptop, label: "Escritorio / Laptop", variant: "violet" },
    { count: mobileCount, icon: Smartphone, label: "Móvil / Tablet", variant: "green" },
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
          <DialogTitle asChild>
            <div className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5 text-muted-foreground" />
              Resumen de dispositivos
            </div>
          </DialogTitle>

          <DialogDescription>
            Aquí puedes ver el detalle de la distribución y estado de tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-4 gap-3">
          <StatCard
            icon={Monitor}
            label="Activos"
            total={total}
            value={stats.active}
            variant="green"
          />
          <StatCard
            icon={Clock}
            label="Próximos a expirar"
            total={total}
            value={stats.expiringSoon}
            variant="amber"
          />
          <StatCard
            icon={Monitor}
            label="Inactivos"
            total={total}
            value={stats.inactive}
            variant="gray"
          />
          <StatCard
            icon={Trash2}
            label="Revocados"
            total={total}
            value={stats.revoked}
            variant="red"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="rounded-lg border p-4">
            <h3 className="text-sm font-semibold">Distribución por estado</h3>
            <p className="mb-4 text-xs text-muted-foreground">
              Porcentaje del total de dispositivos
            </p>

            <div className="flex items-center gap-4">
              <ChartContainer config={chartConfig} className="aspect-square max-h-32 w-32 shrink-0">
                <RadialBarChart
                  data={chartData}
                  endAngle={380}
                  innerRadius={30}
                  outerRadius={110}
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

              <ul className="flex-1 space-y-2">
                {chartData.map((item) => {
                  const colorVariant = colorVariantForEstado[item.estado];
                  const percentage = percentageOf(item.cantidad, total);

                  return (
                    <li className="flex items-center justify-between gap-2" key={item.estado}>
                      <div className="flex items-center gap-2">
                        <div
                          className={cn("h-2.5 w-2.5 rounded-full", solidBarClass(colorVariant))}
                        />

                        <span className="text-sm">{item.label}</span>
                      </div>

                      <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold">{item.cantidad}</span>

                        <span className="w-10 text-right text-xs text-muted-foreground">
                          {percentage}%
                        </span>
                      </div>
                    </li>
                  );
                })}
              </ul>
            </div>
          </div>

          <DeviceTypeBreakdown rows={deviceTypeRows} total={totalByType} />
        </div>

        <Alert className={alertVariants.preview}>
          <CircleAlert />
          <AlertTitle>Mantén tus dispositivos seguros</AlertTitle>
          <AlertDescription>
            Revisa periódicamente los dispositivos que ya no utilizas y elimina aquellos que no
            reconozcas. Esto ayuda a proteger tu cuenta y evitar accesos no autorizados.
          </AlertDescription>
        </Alert>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface DeviceTypeBreakdownProps {
  rows: DeviceTypeRow[];
  total: number;
}

function DeviceTypeBreakdown({ rows, total }: DeviceTypeBreakdownProps): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Dispositivo por tipo</CardTitle>
        <CardDescription>Cantidad de dispositivos por categoría</CardDescription>
      </CardHeader>

      <CardContent className="space-y-4">
        {rows.map((item) => {
          const percentage = percentageOf(item.count, total);
          const Icon = item.icon;

          return (
            <div className="space-y-1.5" key={item.label}>
              <div className="flex items-center gap-3">
                <div
                  className={cn(
                    "flex h-10 w-10 shrink-0 rounded-md p-2",
                    iconColorVariants[item.variant].iconBgClass,
                  )}
                >
                  <Icon className={cn("h-6 w-6", iconColorVariants[item.variant].iconFgClass)} />
                </div>

                <span className="flex-1 text-sm font-medium">{item.label}</span>

                <span className="text-sm font-semibold">{item.count}</span>

                <span className="w-10 text-right text-xs text-muted-foreground">{percentage}%</span>
              </div>

              <Progress
                className={cn("h-1.5", progressBarClassesByVariant[item.variant])}
                value={percentage}
              />
            </div>
          );
        })}

        {total === 0 && (
          <p className="text-sm text-muted-foreground">Aún no tienes dispositivos activos.</p>
        )}
      </CardContent>
    </Card>
  );
}

export default TrustedDeviceSummaryDialog;
