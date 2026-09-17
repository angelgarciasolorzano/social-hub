import type { JSX } from "react";
import { Fragment } from "react";

import { Head, router, usePage } from "@inertiajs/react";

import type { LucideIcon } from "lucide-react";
import {
  ChevronDown,
  ChevronRight,
  Clock4,
  Info,
  MonitorSmartphone,
  MoreHorizontal,
  ShieldCheck,
  ShieldQuestionMark,
  SquarePlus,
} from "lucide-react";

import {
  TrustedDeviceAddDialog,
  TrustedDeviceAlreadyRegisteredDialog,
  TrustedDeviceExpiredDialog,
  TrustedDeviceRevokeAllDialog,
  TrustedDeviceRevokedDialog,
} from "@/modules/setting/modules/trustedDevice/components/dialog";
import TrustedDeviceTable from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTable";
import TrustedDeviceTableToolbar from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableToolbar";
import TrustedDeviceRecentActivity from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity";
import TrustedDeviceRecommendations from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecommendations";
import TrustedDeviceSummary from "@/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummary";
import type { TrustedDevicePerPage } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import {
  trustedDeviceSectionActionKey,
  type TrustedDeviceSectionActionKey,
  trustedDeviceTitleActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import { useTrustedDeviceFilters } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceFilters";
import type {
  TrustedDevice,
  TrustedDeviceActivityItem,
  TrustedDeviceFilters,
  TrustedDevicePagination,
  TrustedDeviceStats,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import type { TrustedDevicePreview } from "@/modules/setting/modules/trustedDevice/types/trustedDevicePreview";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { type IconColorVariant, iconColorVariants } from "@/shared/lib/styling";

import type { SharedData } from "@/shared/types";

type TrustedDevicePageProps = SharedData & {
  currentDevicePreview?: TrustedDevicePreview | null;
  currentDeviceMatch?: TrustedDevice | null;
  filters: TrustedDeviceFilters;
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

  const resolveAddDeviceKind = (
    currentDeviceMatch: TrustedDevice | null | undefined,
  ): SectionDialogState["kind"] => {
    if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
      return trustedDeviceSectionActionKey.addDevice;
    }

    if (currentDeviceMatch.deletedAt !== null) {
      return trustedDeviceSectionActionKey.deviceRevoked;
    }

    if (!currentDeviceMatch.isActive) {
      return trustedDeviceSectionActionKey.deviceExpired;
    }

    return trustedDeviceSectionActionKey.deviceAlreadyRegistered;
  };

  const handleAddDevice = (): void => {
    const kind: SectionDialogState["kind"] = resolveAddDeviceKind(currentDeviceMatch);

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
      case trustedDeviceSectionActionKey.deviceRevoked:
      case trustedDeviceSectionActionKey.deviceExpired:
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
          <TrustedDeviceAddDialog
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
          <TrustedDeviceAlreadyRegisteredDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
            showListLink={false}
          />
        );

      case trustedDeviceSectionActionKey.deviceRevoked:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <TrustedDeviceRevokedDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceSectionActionKey.deviceExpired:
        if (currentDeviceMatch === null || currentDeviceMatch === undefined) {
          return null;
        }

        return (
          <TrustedDeviceExpiredDialog
            existingDevice={currentDeviceMatch}
            open={!isClosing}
            onClose={handleSectionDialogClose}
          />
        );

      case trustedDeviceSectionActionKey.revokeAll:
        return (
          <TrustedDeviceRevokeAllDialog
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
          <TrustedDevicesTableSection />
          <TrustedDevicesSecurityCallout />
        </div>

        <div className="flex w-full max-w-sm shrink-0 flex-col gap-6 self-start">
          <TrustedDeviceSummary />
          <TrustedDeviceRecommendations />
          <TrustedDeviceRecentActivity />
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
            <MoreHorizontal />
            Administrar
            <ChevronDown />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>Acciones del dispositivo</DropdownMenuLabel>

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

function TrustedDevicesTableSection(): JSX.Element {
  const { filters: initialFilters, trustedDevices } = usePage<TrustedDevicePageProps>().props;

  const { committedFilters, filters, resetFilters, updateFilter } =
    useTrustedDeviceFilters(initialFilters);

  const hasActiveFilters =
    committedFilters.search !== "" ||
    committedFilters.status !== null ||
    committedFilters.browser !== null ||
    committedFilters.deviceType !== null ||
    committedFilters.lastAccess !== null;

  return (
    <div className="flex flex-col gap-4">
      <TrustedDeviceTableToolbar
        filters={filters}
        onBrowserFilterChange={(value) => {
          updateFilter("browser", value);
        }}
        onDeviceTypeFilterChange={(value) => {
          updateFilter("deviceType", value);
        }}
        onLastAccessFilterChange={(value) => {
          updateFilter("lastAccess", value);
        }}
        onResetFilters={resetFilters}
        onSearchChange={(value) => {
          updateFilter("search", value);
        }}
        onSortOrderChange={(value) => {
          updateFilter("sort", value);
        }}
        onStatusFilterChange={(value) => {
          updateFilter("status", value);
        }}
      />

      <TrustedDeviceTable
        devices={trustedDevices.data}
        hasActiveFilters={hasActiveFilters}
        pagination={trustedDevices}
        onPerPageChange={(value) => {
          updateFilter("perPage", value as TrustedDevicePerPage);
        }}
      />
    </div>
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
