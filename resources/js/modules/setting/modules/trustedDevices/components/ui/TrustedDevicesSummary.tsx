import type { JSX } from "react";

import { usePage } from "@inertiajs/react";

import { ChevronRight, Circle } from "lucide-react";
import { LabelList, RadialBar, RadialBarChart } from "recharts";

import type { TrustedDeviceStats } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/shared/components/shadcn/ui/chart";
import type { ChartConfig } from "@/shared/components/shadcn/ui/chart";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

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

function TrustedDevicesSummary(): JSX.Element {
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

  return (
    <Card className="flex flex-col">
      <CardHeader className="items-center pb-0">
        <CardTitle>Resumen de dispositivos</CardTitle>
        <CardDescription>Asi esta la seguridad de tus dispositivos</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-1 items-center gap-4 pb-0">
        <ChartContainer config={chartConfig} className="aspect-square max-h-45 w-45 shrink-0">
          <RadialBarChart
            data={chartData}
            startAngle={-90}
            endAngle={380}
            innerRadius={20}
            outerRadius={75}
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

        <ul className="flex flex-1 flex-col justify-center gap-3">
          {chartData.map((item) => {
            const colorVariant = colorVariantForEstado[item.estado];

            return (
              <li className="flex items-center gap-2" key={item.estado}>
                <div
                  className={cn(
                    "flex h-5 w-5 shrink-0 rounded-md p-1",
                    iconColorVariants[colorVariant].iconBgClass,
                  )}
                >
                  <Circle
                    className={cn(
                      "h-3 w-3 fill-current",
                      iconColorVariants[colorVariant].iconFgClass,
                    )}
                  />
                </div>

                <div className="flex flex-col leading-tight">
                  <span className="text-sm font-medium">{item.label}</span>
                  <span className="text-sm text-muted-foreground">{item.cantidad}</span>
                </div>
              </li>
            );
          })}
        </ul>
      </CardContent>
      <CardFooter className="mx-auto">
        <Button variant="link" className="text-blue-700 dark:text-blue-500">
          Ver detalles
          <ChevronRight className="h-4 w-4" />
        </Button>
      </CardFooter>
    </Card>
  );
}

export default TrustedDevicesSummary;
