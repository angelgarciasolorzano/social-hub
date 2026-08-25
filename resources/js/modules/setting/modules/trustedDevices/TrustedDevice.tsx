import type { JSX } from "react";
import { Fragment } from "react";

import { Head, Link, usePage } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  Circle,
  Clock4,
  Funnel,
  Info,
  MonitorSmartphone,
  MoreHorizontalIcon,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  ShieldQuestionMark,
  SquarePlus,
  Trash2,
  UserPlus,
  X,
} from "lucide-react";
import { LabelList, RadialBar, RadialBarChart } from "recharts";

import { trustedDeviceRecommendations } from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";
import type {
  TrustedDevice,
  TrustedDeviceAction,
  TrustedDeviceActivityItem,
  TrustedDevicePagination,
  TrustedDeviceStats,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Separator } from "@/shared/components/shadcn/ui/separator";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/shared/components/shadcn/ui/table";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

type TrustedDevicePageProps = SharedData & {
  trustedDevices: TrustedDevicePagination;
  stats: TrustedDeviceStats;
  recentActivity: TrustedDeviceActivityItem[];
};

function TrustedDevice(): JSX.Element {
  return (
    <>
      <Head title="Two Factor Authentication" />
      <div className="flex gap-4">
        <div className="flex min-w-0 flex-1 flex-col gap-4">
          <TrustedDeviceTitle />
          <TrustedDevicesStatCards />
          <TrustedDevicesInfoBanner />
          <TrustedDevicesTable />
          <TrustedDevicesSecurityCallout />
        </div>

        <div className="flex w-full max-w-sm shrink-0 flex-col gap-6 self-start">
          <TrustedDevicesSummary />
          <TrustedDevicesRecommendations />
          <TrustedDevicesRecentActivity />
        </div>
      </div>
    </>
  );
}

export default TrustedDevice;

function TrustedDeviceTitle(): JSX.Element {
  return (
    <div className="flex items-center justify-between gap-12 rounded-xl border bg-card p-6 shadow-sm">
      <div className="flex items-start gap-6">
        <div className={cn(iconColorVariants.blue.iconBgClass, "rounded-3xl p-2")}>
          <ShieldCheck className={cn("h-12 w-12", iconColorVariants.blue.iconFgClass)} />
        </div>

        <div className="flex flex-1 flex-col gap-3">
          <div className="flex flex-col gap-1">
            <h2 className="text-xl font-semibold">Dispositivos de confianza</h2>

            <p className="text-sm text-muted-foreground">
              Estos son los dispositivos en los que has iniciado sesion y que has marcado como de
              confianza.
            </p>
          </div>
        </div>
      </div>

      <Button>
        Agregar dispositivo
        <Plus className="h-4 w-4" />
      </Button>
    </div>
  );
}

interface TrustedDeviceStatCard {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
  value: number;
}

function TrustedDevicesStatCards(): JSX.Element {
  const { stats } = usePage<TrustedDevicePageProps>().props;

  const trustedDevicesStatCards: TrustedDeviceStatCard[] = [
    {
      description: "Dispositivos registrados",
      icon: MonitorSmartphone,
      iconColor: "blue",
      title: "Total de dispositivos",
      value: stats.total,
    },
    {
      description: "Actualmente pueden iniciar sesión",
      icon: ShieldCheck,
      iconColor: "green",
      title: "Dispositivos activos",
      value: stats.active,
    },
    {
      description: "En los proximos 7 días",
      icon: Clock4,
      iconColor: "purple",
      title: "Proximos a expirar",
      value: stats.expiringSoon,
    },
    {
      description: "En los ultimos 7 días",
      icon: SquarePlus,
      iconColor: "orange",
      title: "Agregado recientemente",
      value: stats.recentlyAdded,
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {trustedDevicesStatCards.map(({ icon: Icon, ...card }) => (
        <div
          className="flex h-full items-start gap-4 rounded-xl border bg-card p-4 shadow-sm"
          key={card.title}
        >
          <div
            className={cn(
              "flex h-12 w-12 shrink-0 rounded-md p-2",
              iconColorVariants[card.iconColor].iconBgClass,
            )}
          >
            <Icon className={cn("h-8 w-8", iconColorVariants[card.iconColor].iconFgClass)} />
          </div>

          <div className="flex w-full min-w-0 flex-col gap-2">
            <h4 className="truncate text-2xl font-medium">{card.value}</h4>

            <div className="flex flex-col gap-1">
              <span className="truncate text-sm font-semibold">{card.title}</span>
              <p className="line-clamp-2 text-sm text-muted-foreground">{card.description}</p>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

function TrustedDevicesInfoBanner() {
  return (
    <Alert>
      <Info />
      <AlertTitle>
        Los dispositivos de confianza te permiten iniciar sesion mas rapido y mantener tu cuenta
        protegida.
      </AlertTitle>
      <AlertDescription className="flex cursor-pointer items-center font-semibold text-blue-700 hover:underline dark:text-blue-500">
        Mas información sobre dispositivos de confianza <ChevronRight className="h-4 w-4" />
      </AlertDescription>
    </Alert>
  );
}

function TrustedDevicesTable(): JSX.Element {
  const { trustedDevices } = usePage<TrustedDevicePageProps>().props;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <InputGroup className="max-w-xs">
          <InputGroupAddon>
            <Search className="size-4" />
          </InputGroupAddon>
          <InputGroupInput placeholder="Buscar dispositivo..." />
        </InputGroup>

        <div className="flex items-center justify-center gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger>
              <Button variant="outline">
                <Funnel />
                Filtros
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>Edit</DropdownMenuItem>
              <DropdownMenuItem>Duplicate</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger>
              <Button variant="outline">
                <Calendar />
                Mas recientes
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>Edit</DropdownMenuItem>
              <DropdownMenuItem>Duplicate</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button variant="outline">
            <RefreshCw />
            <span className="sr-only">Reload data</span>
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Badge variant="outline" className="rounded-md py-2">
          Estado: <span className="font-semibold">Activo</span>
          <X data-icon="inline-end" />
        </Badge>

        <Badge variant="outline" className="rounded-md py-2">
          Tipo: <span className="font-semibold">Laptop</span>
          <X data-icon="inline-end" />
        </Badge>

        <Badge variant="outline" className="rounded-md py-2">
          Navegador: <span className="font-semibold">Chrome</span>
          <X data-icon="inline-end" />
        </Badge>

        <Button variant="ghost">Limpiar filtros</Button>
      </div>

      <div className="w-full">
        <div className="[&>div]:max-h-140 [&>div]:min-h-130 [&>div]:rounded-md [&>div]:border">
          <Table>
            <TableHeader>
              <TableRow className="sticky top-0 bg-muted/70 dark:bg-muted/40">
                <TableHead>Dispositivo</TableHead>
                <TableHead>Ultimo Acceso</TableHead>
                <TableHead>Navegador / SO</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {trustedDevices.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                    No tienes dispositivos de confianza registrados.
                  </TableCell>
                </TableRow>
              ) : (
                trustedDevices.data.map((device) => (
                  <TrustedDeviceRow key={device.id} device={device} />
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {trustedDevices.last_page > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">
            Mostrando {trustedDevices.from}–{trustedDevices.to} de {trustedDevices.total}
          </span>
          <div className="flex items-center gap-2">
            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={trustedDevices.prev_page_url === null}
            >
              <Link href={trustedDevices.prev_page_url ?? ""} preserveScroll>
                <ChevronLeft />
                Anterior
              </Link>
            </Button>

            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={trustedDevices.next_page_url === null}
            >
              <Link href={trustedDevices.next_page_url ?? ""} preserveScroll>
                Siguiente
                <ChevronRight />
              </Link>
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

interface TrustedDeviceRowProps {
  device: TrustedDevice;
}

function TrustedDeviceRow({ device }: TrustedDeviceRowProps): JSX.Element {
  const deviceLabel = device.name ?? `${device.browser ?? "Desconocido"} - ${device.osName ?? "?"}`;
  const browserAndOs = [device.browser, device.osName]
    .filter((value) => value !== null)
    .join(" / ");

  return (
    <TableRow>
      <TableCell className="font-medium">{deviceLabel}</TableCell>
      <TableCell className="text-muted-foreground">{fromNow(device.lastUsedAt)}</TableCell>
      <TableCell className="text-muted-foreground">{browserAndOs || "Desconocido"}</TableCell>
      <TableCell>
        <Badge variant={device.isActive ? "default" : "destructive"} className="rounded-md">
          {device.isActive ? "Activo" : "Expirado"}
        </Badge>
      </TableCell>
      <TableCell>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="size-8">
              <MoreHorizontalIcon />
              <span className="sr-only">Open menu</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem>
              <Pencil />
              Renombrar
            </DropdownMenuItem>
            <DropdownMenuItem>
              <RefreshCw />
              Renovar
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive">
              <Trash2 />
              Revocar
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>
    </TableRow>
  );
}

function TrustedDevicesSecurityCallout(): JSX.Element {
  return (
    <div className="flex items-center justify-between gap-4 rounded-xl border bg-card p-4 shadow-sm">
      <div className="flex items-start gap-4">
        <div className={cn("flex h-10 w-10 rounded-md p-2", iconColorVariants.blue.iconBgClass)}>
          <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
        </div>

        <div className="flex w-full items-center justify-between gap-4">
          <div className="space-y-1">
            <h4 className="text-sm font-semibold">¿No reconoces algun dispositivo?</h4>

            <p className="text-sm text-muted-foreground">
              Revoca su acceso desde el menu del dispositivo. Si detectas actividad sospechosa,
              revisa la actividad de tu cuenta.
            </p>
          </div>
        </div>
      </div>

      <Button variant="link" className="text-blue-700 dark:text-blue-500">
        Revisar actividad de seguridad
        <ChevronRight className="h-4 w-4" />
      </Button>
    </div>
  );
}

interface TrustedDeviceSummaryDatum {
  estado: "activos" | "porExpirar" | "inactivos" | "revocados";
  label: string;
  cantidad: number;
  fill: string;
}

function TrustedDevicesSummary(): JSX.Element {
  const { stats } = usePage<TrustedDevicePageProps>().props;

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
    porExpirar: "amber",
    inactivos: "gray",
    revocados: "red",
  };

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

function TrustedDevicesRecommendations(): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Recomendaciones</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {trustedDeviceRecommendations.map((recommendation) => {
          const Icon = recommendation.icon;

          return (
            <div className="flex items-center justify-between gap-4" key={recommendation.title}>
              <div className="flex items-start gap-4">
                <div
                  className={cn(
                    "flex h-10 w-10 rounded-full p-2",
                    iconColorVariants[recommendation.iconColor].iconBgClass,
                  )}
                >
                  <Icon
                    className={cn(
                      "h-6 w-6",
                      iconColorVariants[recommendation.iconColor].iconFgClass,
                    )}
                  />
                </div>

                <div className="space-y-1.5">
                  <h4 className="text-sm font-semibold">{recommendation.title}</h4>

                  <p className="text-sm text-muted-foreground">{recommendation.description}</p>
                </div>
              </div>
            </div>
          );
        })}
      </CardContent>
      <CardFooter className="mx-auto">
        <Button variant="link" className="text-blue-700 dark:text-blue-500">
          Mas recomendaciones
          <ChevronRight className="h-4 w-4" />
        </Button>
      </CardFooter>
    </Card>
  );
}

function TrustedDevicesRecentActivity(): JSX.Element {
  const { recentActivity } = usePage<TrustedDevicePageProps>().props;

  const iconForAction: Record<TrustedDeviceAction, LucideIcon> = {
    created: UserPlus,
    renewed: RefreshCw,
    renamed: Pencil,
    revoked: Trash2,
    revoked_all: Trash2,
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Actividad reciente</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {recentActivity.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground">
            <ShieldQuestionMark className="h-8 w-8" />
            <span>Sin actividad reciente registrada.</span>
          </div>
        ) : (
          recentActivity.map((item) => {
            const Icon = iconForAction[item.action];

            return (
              <Fragment key={item.id}>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div
                      className={cn(
                        "flex h-10 w-10 rounded-full p-2",
                        iconColorVariants.blue.iconBgClass,
                      )}
                    >
                      <Icon className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
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
