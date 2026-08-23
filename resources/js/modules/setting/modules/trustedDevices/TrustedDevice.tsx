import type { JSX } from "react";

import { Head } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import {
  Calendar,
  ChevronRight,
  Clock4,
  Funnel,
  Info,
  MonitorSmartphone,
  MoreHorizontalIcon,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  ShieldQuestionMark,
  SquarePlus,
  X,
} from "lucide-react";

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

function TrustedDevice(): JSX.Element {
  return (
    <>
      <Head title="Two Factor Authentication" />
      <div className="flex gap-4">
        <div className="flex min-w-0 flex-1 flex-col gap-4">
          <TrustedDeviceTitle />
          <TrustedDevicesStatCards />
          <TustedDevicesInfoBanner />
          <TrustedDevicesTable />
          <TrustedDevicesFooter />
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
  value: string;
}

function TrustedDevicesStatCards(): JSX.Element {
  const trustedDevicesStatCards: TrustedDeviceStatCard[] = [
    {
      description: "Dispositivos registrados",
      icon: MonitorSmartphone,
      iconColor: "blue",
      title: "Total de dispositivos",
      value: "5",
    },
    {
      description: "Actualmente pueden iniciar sesión",
      icon: ShieldCheck,
      iconColor: "green",
      title: "Dispositivos activos",
      value: "5",
    },
    {
      description: "En los proximos 30 días",
      icon: Clock4,
      iconColor: "purple",
      title: "Proximos a expirar",
      value: "1",
    },
    {
      description: "En los ultimos 7 días",
      icon: SquarePlus,
      iconColor: "orange",
      title: "Agregado recientemente",
      value: "1",
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

function TustedDevicesInfoBanner() {
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

      <div className="flex items-center gap-2">
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
        <div className="[&>div]:max-h-100 [&>div]:min-h-90 [&>div]:rounded-md [&>div]:border">
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
              <TableRow>
                <TableCell>Laptop</TableCell>
                <TableCell>Hace 2 horas</TableCell>
                <TableCell>Chrome / MacOS</TableCell>
                <TableCell>Activo</TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger>
                      <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontalIcon />
                        <span className="sr-only">Open menu</span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem>Edit</DropdownMenuItem>
                      <DropdownMenuItem>Duplicate</DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
              <TableRow>
                <TableCell>Laptop</TableCell>
                <TableCell>Hace 2 horas</TableCell>
                <TableCell>Chrome / MacOS</TableCell>
                <TableCell>Activo</TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger>
                      <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontalIcon />
                        <span className="sr-only">Open menu</span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem>Edit</DropdownMenuItem>
                      <DropdownMenuItem>Duplicate</DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem variant="destructive">Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}

function TrustedDevicesFooter(): JSX.Element {
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

function TrustedDevicesSummary(): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Resumen de dispositivos</CardTitle>
        <CardDescription>Asi esta la seguridad de tus dispositivos</CardDescription>
      </CardHeader>
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
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-sm font-semibold">Revisa tus dispositivos periodicamente</h4>

              <p className="text-sm text-muted-foreground">Elimina los que ya no utilizas.</p>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-sm font-semibold">No confies en dispositivos compartidos</h4>

              <p className="text-sm text-muted-foreground">
                Marca solo equipos que esten bajo tu control.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-sm font-semibold">Revoca accesos que no reconzcas</h4>

              <p className="text-sm text-muted-foreground">
                Si ves algo extraño, elimina ese dispositivo.
              </p>
            </div>
          </div>
        </div>
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
  return (
    <Card>
      <CardHeader>
        <CardTitle>Actividad reciente</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="flex w-full items-center justify-between gap-4">
              <div className="space-y-1">
                <h4 className="text-sm font-semibold">MacBook Pro de Angel</h4>

                <p className="text-sm text-muted-foreground">Inicio de sesion</p>
              </div>
            </div>
          </div>

          <p className="text-sm text-muted-foreground">Hace 6 minutos</p>
        </div>

        <Separator />

        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="flex w-full items-center justify-between gap-4">
              <div className="space-y-1">
                <h4 className="text-sm font-semibold">MacBook Pro de Angel</h4>

                <p className="text-sm text-muted-foreground">Inicio de sesion</p>
              </div>
            </div>
          </div>

          <p className="text-sm text-muted-foreground">Hace 6 minutos</p>
        </div>

        <Separator />

        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-4">
            <div
              className={cn("flex h-10 w-10 rounded-full p-2", iconColorVariants.blue.iconBgClass)}
            >
              <ShieldQuestionMark className={cn("h-6 w-6", iconColorVariants.blue.iconFgClass)} />
            </div>

            <div className="flex w-full items-center justify-between gap-4">
              <div className="space-y-1">
                <h4 className="text-sm font-semibold">MacBook Pro de Angel</h4>

                <p className="text-sm text-muted-foreground">Inicio de sesion</p>
              </div>
            </div>
          </div>

          <p className="text-sm text-muted-foreground">Hace 6 minutos</p>
        </div>

        <Separator />
      </CardContent>
      <CardFooter className="mx-auto">
        <Button variant="link" className="text-blue-700 dark:text-blue-500">
          Ver toda la actividad
          <ChevronRight className="h-4 w-4" />
        </Button>
      </CardFooter>
    </Card>
  );
}
