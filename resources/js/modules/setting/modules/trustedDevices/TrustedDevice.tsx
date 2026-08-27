import type { JSX } from "react";
import { Fragment, useMemo, useState } from "react";

import { Head, Link, router, usePage } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import {
  ArrowDownNarrowWide,
  Bolt,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Circle,
  Clock4,
  Funnel,
  Info,
  MonitorSmartphone,
  MoreHorizontalIcon,
  Pencil,
  RefreshCw,
  RotateCcw,
  Search,
  ShieldCheck,
  ShieldQuestionMark,
  SquarePlus,
  Trash2,
  UserPlus,
} from "lucide-react";
import { LabelList, RadialBar, RadialBarChart } from "recharts";

import {
  AddDeviceDialog,
  DeviceAlreadyRegisteredDialog,
  DeviceDetailsDialog,
  RenameDeviceDialog,
  RenewTrustDialog,
  RevokeAllDevicesDialog,
  RevokeDeviceDialog,
} from "@/modules/setting/modules/trustedDevices/components/dialog";
import {
  browserOptions,
  deviceTypeOptions,
  lastAccessOptions,
  statusOptions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import {
  defaultTrustedDeviceSort,
  type TrustedDeviceSortKey,
  trustedDeviceSortOptions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceSort";
import {
  trustedDeviceRecommendations,
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
  trustedDeviceSectionActionKey,
  type TrustedDeviceSectionActionKey,
  trustedDeviceTitleActions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";
import type { DevicePreview } from "@/modules/setting/modules/trustedDevices/types/devicePreview";
import type {
  TrustedDevice,
  TrustedDeviceAction,
  TrustedDeviceActivityItem,
  TrustedDevicePagination,
  TrustedDeviceStats,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";
import {
  deviceBrowserAndOs,
  deviceLabel,
  getDeviceIcon,
} from "@/modules/setting/shared/utils/trustedDevice";

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
  Combobox,
  ComboboxContent,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/shared/components/shadcn/ui/combobox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/shared/components/shadcn/ui/radio-group";
import { Separator } from "@/shared/components/shadcn/ui/separator";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/shared/components/shadcn/ui/table";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

type TrustedDevicePageProps = SharedData & {
  currentDevicePreview?: DevicePreview | null;
  currentDeviceMatch?: TrustedDevice | null;
  trustedDevices: TrustedDevicePagination;
  trustedDevicesForRevoke?: TrustedDevice[];
  stats: TrustedDeviceStats;
  recentActivity: TrustedDeviceActivityItem[];
};

interface SectionDialogState extends DialogClosingState {
  kind: TrustedDeviceSectionActionKey;
}

function TrustedDevice(): JSX.Element {
  const {
    currentDevicePreview,
    currentDeviceMatch,
    trustedDevicesForRevoke = [],
  } = usePage<TrustedDevicePageProps>().props;

  const sectionDialog = useDialog<SectionDialogState | null>(null);

  const handleAddDevice = (): void => {
    const kind: SectionDialogState["kind"] =
      currentDeviceMatch !== null && currentDeviceMatch !== undefined
        ? "device-already-registered"
        : "add-device";

    sectionDialog.show({ kind, closing: false });
  };

  const handleRevokeAllDevices = (): void => {
    router.reload({
      only: ["trustedDevicesForRevoke"],
      onSuccess: () => {
        sectionDialog.show({ kind: trustedDeviceSectionActionKey.revokeAll, closing: false });
      },
    });
  };

  const handleTitleAction = (action: TrustedDeviceSectionActionKey): void => {
    switch (action) {
      case trustedDeviceSectionActionKey.addDevice:
      case trustedDeviceSectionActionKey.deviceAlreadyRegistered:
        handleAddDevice();

        break;

      case trustedDeviceSectionActionKey.revokeAll:
        handleRevokeAllDevices();

        break;

      default:
        break;
    }
  };

  const handleSectionDialogClose = createDialogCloseHandler(sectionDialog);

  const renderSectionDialog = (): JSX.Element | null => {
    if (sectionDialog.state === null) {
      return null;
    }

    const isClosing = sectionDialog.state.closing;

    switch (sectionDialog.state.kind) {
      case trustedDeviceSectionActionKey.addDevice:
        return (
          <AddDeviceDialog
            preview={currentDevicePreview ?? null}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceSectionActionKey.deviceAlreadyRegistered:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <DeviceAlreadyRegisteredDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceSectionActionKey.revokeAll:
        return (
          <RevokeAllDevicesDialog
            devices={trustedDevicesForRevoke}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      default:
        return null;
    }
  };

  return (
    <>
      <Head title="Dispositivos de confianza" />
      <div className="flex gap-4">
        <div className="flex min-w-0 flex-1 flex-col gap-4">
          <TrustedDeviceTitle onTitleAction={handleTitleAction} />
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

      {renderSectionDialog()}
    </>
  );
}

export default TrustedDevice;

interface TrustedDeviceTitleProps {
  onTitleAction: (action: TrustedDeviceSectionActionKey) => void;
}

function TrustedDeviceTitle({ onTitleAction }: TrustedDeviceTitleProps): JSX.Element {
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

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline">
            <Bolt />
            Administrar
            <ChevronDown />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {trustedDeviceTitleActions.map((group, groupIndex) => (
            <Fragment key={groupIndex}>
              <DropdownMenuGroup>
                {group.label !== undefined && <DropdownMenuLabel>{group.label}</DropdownMenuLabel>}

                {group.actions.map((action) => {
                  const Icon = action.icon;

                  return (
                    <DropdownMenuItem
                      key={action.key}
                      onClick={() => {
                        onTitleAction(action.key);
                      }}
                      className={action.className}
                    >
                      <Icon className={action.iconClassName} />
                      {action.label}
                    </DropdownMenuItem>
                  );
                })}
              </DropdownMenuGroup>

              {groupIndex < trustedDeviceTitleActions.length - 1 && <DropdownMenuSeparator />}
            </Fragment>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
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

  const [statusFilter, setStatusFilter] = useState<string | null>(null);
  const [deviceTypeFilter, setDeviceTypeFilter] = useState<string | null>(null);
  const [browserFilter, setBrowserFilter] = useState<string | null>(null);
  const [lastAccessFilter, setLastAccessFilter] = useState<string | null>(null);
  const [sortOrder, setSortOrder] = useState<TrustedDeviceSortKey>(defaultTrustedDeviceSort);

  const selectedSortLabel =
    trustedDeviceSortOptions.find((option) => option.value === sortOrder)?.label ?? "Ordenar por";

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
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline">
                <Funnel />
                Filtros
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 p-3">
              <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-sm font-semibold">Filtros</h4>

                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-blue-700 hover:bg-blue-100/50 hover:text-blue-700 dark:text-blue-500 dark:hover:bg-blue-900/20 dark:hover:text-blue-500"
                  >
                    Restablecer
                  </Button>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">Estado</label>
                  <Combobox value={statusFilter} onValueChange={setStatusFilter}>
                    <ComboboxInput showClear placeholder="Estado" />
                    <ComboboxContent>
                      <ComboboxList>
                        {statusOptions.map((opt) => (
                          <ComboboxItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </ComboboxItem>
                        ))}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">
                    Tipo de dispositivo
                  </label>
                  <Combobox value={deviceTypeFilter} onValueChange={setDeviceTypeFilter}>
                    <ComboboxInput showClear placeholder="Tipo de dispositivo" />
                    <ComboboxContent>
                      <ComboboxList>
                        {deviceTypeOptions.map((opt) => (
                          <ComboboxItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </ComboboxItem>
                        ))}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">
                    Navegador / SO
                  </label>
                  <Combobox value={browserFilter} onValueChange={setBrowserFilter}>
                    <ComboboxInput showClear placeholder="Navegador / SO" />
                    <ComboboxContent>
                      <ComboboxList>
                        {browserOptions.map((opt) => (
                          <ComboboxItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </ComboboxItem>
                        ))}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">Último acceso</label>
                  <Combobox value={lastAccessFilter} onValueChange={setLastAccessFilter}>
                    <ComboboxInput showClear placeholder="Último acceso" />
                    <ComboboxContent>
                      <ComboboxList>
                        {lastAccessOptions.map((opt) => (
                          <ComboboxItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </ComboboxItem>
                        ))}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>

                <div className="flex justify-between gap-2">
                  <Button variant="outline">Limpiar filtros</Button>

                  <Button>Aplicar filtros</Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>

          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline">
                <ArrowDownNarrowWide />
                {selectedSortLabel}
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-3">
              <div className="flex flex-col gap-3">
                <h4 className="text-sm font-semibold">Ordenar por</h4>

                <RadioGroup
                  value={sortOrder}
                  onValueChange={(value) => {
                    setSortOrder(value as TrustedDeviceSortKey);
                  }}
                  className="gap-1"
                >
                  {trustedDeviceSortOptions.map((option) => (
                    <label
                      key={option.value}
                      htmlFor={`sort-${option.value}`}
                      className="flex cursor-pointer items-start gap-3 rounded-md p-2 transition-colors hover:bg-accent/50 has-data-[state=checked]:bg-blue-100/50 has-data-[state=checked]:dark:bg-blue-950/20"
                    >
                      <RadioGroupItem
                        id={`sort-${option.value}`}
                        value={option.value}
                        className="mt-0.5"
                      />
                      <div className="flex flex-1 flex-col gap-0.5 leading-tight">
                        <span className="text-sm font-medium">{option.label}</span>
                        <span className="text-xs text-muted-foreground">{option.description}</span>
                      </div>
                    </label>
                  ))}
                </RadioGroup>

                <Separator />

                <div className="flex items-center justify-between">
                  <div className="flex flex-col leading-tight">
                    <span className="text-sm font-medium">Predeterminado</span>
                    <span className="text-xs text-muted-foreground">{selectedSortLabel}</span>
                  </div>

                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setSortOrder(defaultTrustedDeviceSort);
                    }}
                  >
                    <RotateCcw />
                    Restablecer
                  </Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>

          <Button variant="outline">
            <RefreshCw />
            <span className="sr-only">Reload data</span>
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
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
                  <TableCell colSpan={5} className="p-0">
                    <EmptyState
                      icon={MonitorSmartphone}
                      title="No tienes dispositivos de confianza registrados."
                    />
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

interface RowDialogActionState extends DialogClosingState {
  kind: (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];
  device: TrustedDevice;
}

interface TrustedDeviceRowProps {
  device: TrustedDevice;
}

function TrustedDeviceRow({ device }: TrustedDeviceRowProps): JSX.Element {
  const { trustedDevices } = usePage<TrustedDevicePageProps>().props;

  const browserAndOs = deviceBrowserAndOs(device);

  const dialogDevice = useDialog<RowDialogActionState | null>(null);

  const handleDeviceAction = (
    action: RowDialogActionState["kind"],
    targetDevice: TrustedDevice,
  ): void => {
    dialogDevice.show({ kind: action, device: targetDevice, closing: false });
  };

  const handleDialogClose = createDialogCloseHandler(dialogDevice);

  const renderDialogDevice = (): JSX.Element | null => {
    if (dialogDevice.state === null) {
      return null;
    }

    const isClosing = dialogDevice.state.closing;

    const selectedDevice =
      trustedDevices.data.find((device) => device.id === dialogDevice.state?.device.id) ?? null;

    if (selectedDevice === null) {
      return null;
    }

    switch (dialogDevice.state.kind) {
      case trustedDeviceRowActionKey.viewDevice:
        return (
          <DeviceDetailsDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.renameDevice:
        return (
          <RenameDeviceDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      case trustedDeviceRowActionKey.renewTrust:
        return (
          <RenewTrustDialog device={selectedDevice} open={!isClosing} onClose={handleDialogClose} />
        );

      case trustedDeviceRowActionKey.revokeDevice:
        return (
          <RevokeDeviceDialog
            device={selectedDevice}
            open={!isClosing}
            onClose={handleDialogClose}
          />
        );

      default:
        return null;
    }
  };

  return (
    <TableRow>
      <TableCell className="font-medium">
        <div className="flex items-center gap-2">
          {getDeviceIcon(device, "h-4 w-4 shrink-0 text-muted-foreground")}
          <span className="truncate">{deviceLabel(device)}</span>
        </div>
      </TableCell>
      <TableCell className="text-muted-foreground">{fromNow(device.lastUsedAt)}</TableCell>
      <TableCell className="text-muted-foreground">{browserAndOs}</TableCell>
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
          <DropdownMenuContent align="end" className="w-60">
            {trustedDeviceRowActions.map((group, groupIndex) => (
              <Fragment key={groupIndex}>
                <DropdownMenuGroup>
                  {group.label !== undefined && (
                    <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                  )}

                  {group.actions.map((action) => {
                    const Icon = action.icon;

                    return (
                      <DropdownMenuItem
                        key={action.key}
                        onClick={() => {
                          handleDeviceAction(action.key, device);
                        }}
                        className={action.className}
                      >
                        <Icon className={action.iconClassName} />
                        {action.label}
                      </DropdownMenuItem>
                    );
                  })}
                </DropdownMenuGroup>

                {groupIndex < trustedDeviceRowActions.length - 1 && <DropdownMenuSeparator />}
              </Fragment>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>

      {renderDialogDevice()}
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

  const actionVisuals = useMemo<
    Record<TrustedDeviceAction, { icon: LucideIcon; color: IconColorVariant }>
  >(
    () => ({
      created: { icon: UserPlus, color: "blue" },
      renewed: { icon: RefreshCw, color: "green" },
      renamed: { icon: Pencil, color: "purple" },
      revoked: { icon: Trash2, color: "red" },
      revoked_all: { icon: Trash2, color: "red" },
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
