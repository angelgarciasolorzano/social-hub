import type { JSX } from "react";

import { usePage } from "@inertiajs/react";

import { Laptop, Monitor, Smartphone } from "lucide-react";
import { LabelList, RadialBar, RadialBarChart } from "recharts";

import type { TrustedDeviceStats } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
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

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceSummaryDialogProps {
  open: boolean;
  onClose: () => void;
}

interface TrustedDeviceSummaryDatum {
  estado: "activos" | "porExpirar" | "inactivos" | "revocados";
  label: string;
  cantidad: number;
  fill: string;
}

const colorVariantForEstado: Record<TrustedDeviceSummaryDatum["estado"], IconColorVariant> = {
  activos: "green",
  porExpirar: "amber",
  inactivos: "gray",
  revocados: "red",
};

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

function TrustedDeviceSummaryDialog({
  open,
  onClose,
}: TrustedDeviceSummaryDialogProps): JSX.Element {
  const { stats } = usePage<{ stats: TrustedDeviceStats }>().props;

  const chartData: TrustedDeviceSummaryDatum[] = [
    {
      estado: "activos",
      label: "Activos",
      cantidad: Math.max(0, stats.active - stats.expiringSoon),
      fill: "var(--color-activos)",
    },
    {
      estado: "porExpirar",
      label: "Próximos a expirar",
      cantidad: stats.expiringSoon,
      fill: "var(--color-por-expirar)",
    },
    {
      estado: "inactivos",
      label: "Inactivos",
      cantidad: stats.inactive,
      fill: "var(--color-inactivos)",
    },
    {
      estado: "revocados",
      label: "Revocados",
      cantidad: stats.revoked,
      fill: "var(--color-revocados)",
    },
  ];

  const desktopCount = stats.byDeviceType.desktop;
  const mobileCount = stats.byDeviceType.mobile;
  const totalByType = desktopCount + mobileCount;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Resumen de dispositivos</DialogTitle>
          <DialogDescription>
            Aquí puedes ver el detalle de la distribución y estado de tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col items-center gap-6">
          <ChartContainer config={chartConfig} className="aspect-square max-h-60 w-60">
            <RadialBarChart
              data={chartData}
              startAngle={-90}
              endAngle={380}
              innerRadius={30}
              outerRadius={110}
            >
              <ChartTooltip
                cursor={false}
                content={<ChartTooltipContent hideLabel nameKey="estado" />}
              />

              <RadialBar dataKey="cantidad" background>
                <LabelList
                  position="insideStart"
                  dataKey="label"
                  className="fill-white capitalize mix-blend-luminosity"
                  fontSize={11}
                />
              </RadialBar>
            </RadialBarChart>
          </ChartContainer>

          <ul className="flex w-full flex-col gap-3">
            {chartData.map((item) => {
              const colorVariant = colorVariantForEstado[item.estado];

              return (
                <li className="flex items-center justify-between gap-2" key={item.estado}>
                  <div className="flex items-center gap-2">
                    <div
                      className={cn(
                        "flex h-5 w-5 shrink-0 rounded-md p-1",
                        iconColorVariants[colorVariant].iconBgClass,
                      )}
                    >
                      <Monitor
                        className={cn("h-3 w-3", iconColorVariants[colorVariant].iconFgClass)}
                      />
                    </div>

                    <span className="text-sm font-medium">{item.label}</span>
                  </div>

                  <span className="text-sm text-muted-foreground">{item.cantidad}</span>
                </li>
              );
            })}
          </ul>
        </div>

        <div className="space-y-3">
          <h3 className="text-sm font-semibold">Dispositivos por tipo</h3>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-center gap-3 rounded-lg border p-4">
              <div
                className={cn(
                  "flex h-10 w-10 shrink-0 rounded-full p-2",
                  iconColorVariants.blue.iconBgClass,
                )}
              >
                <Laptop className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
              </div>

              <div className="flex flex-col">
                <span className="text-sm font-medium">Escritorio / Laptop</span>
                <span className="text-2xl font-semibold">{desktopCount}</span>
              </div>
            </div>

            <div className="flex items-center gap-3 rounded-lg border p-4">
              <div
                className={cn(
                  "flex h-10 w-10 shrink-0 rounded-full p-2",
                  iconColorVariants.green.iconBgClass,
                )}
              >
                <Smartphone className={cn("h-6 w-6", iconColorVariants.green.iconFgClass)} />
              </div>

              <div className="flex flex-col">
                <span className="text-sm font-medium">Móvil / Tablet</span>
                <span className="text-2xl font-semibold">{mobileCount}</span>
              </div>
            </div>
          </div>

          {totalByType === 0 && (
            <p className="text-sm text-muted-foreground">Aún no tienes dispositivos activos.</p>
          )}
        </div>

        <Alert>
          <AlertTitle>Mantén tus dispositivos seguros</AlertTitle>
          <AlertDescription>
            Mantén tus dispositivos actualizados y revisa periódicamente los que ya no utilizas.
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

export default TrustedDeviceSummaryDialog;
