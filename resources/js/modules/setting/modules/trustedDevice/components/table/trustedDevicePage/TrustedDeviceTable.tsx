import type { JSX } from "react";
import { Fragment } from "react";

import { usePage } from "@inertiajs/react";

import { createColumnHelper, tableFeatures, useTable } from "@tanstack/react-table";
import { MonitorSmartphone, MoreHorizontalIcon, SearchX } from "lucide-react";

import {
  TrustedDeviceDetailsDialog,
  TrustedDeviceForceDestroyDialog,
  TrustedDeviceReactivationDialog,
  TrustedDeviceRenameDialog,
  TrustedDeviceRenewTrustDialog,
  TrustedDeviceRevokeDialog,
} from "@/modules/setting/modules/trustedDevice/components/dialog";
import TrustedDevicePagination from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDevicePagination";
import {
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import type {
  TrustedDevice,
  TrustedDevicePagination as TrustedDevicePaginationData,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { fromNow } from "@/modules/setting/shared/utils/dateTime";
import { createDialogCloseHandler } from "@/modules/setting/shared/utils/dialog";
import type { DialogClosingState } from "@/modules/setting/shared/utils/dialog";
import {
  deviceBrowserAndOs,
  deviceLabel,
  getDeviceIcon,
} from "@/modules/setting/shared/utils/trustedDevice";

import { Badge } from "@/shared/components/shadcn/ui/badge";
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/shared/components/shadcn/ui/table";

import { useAppearance, useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";

import type { SharedData } from "@/shared/types";

type TrustedDeviceRowDialogActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

const trustedDeviceTableFeatures = tableFeatures({});

const trustedDeviceColumnHelper = createColumnHelper<
  typeof trustedDeviceTableFeatures,
  TrustedDevice
>();

const trustedDeviceTableColumns = trustedDeviceColumnHelper.columns([
  trustedDeviceColumnHelper.display({
    id: "device",
    header: "Dispositivo",
    cell: ({ row }) => <TrustedDeviceDeviceCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: "lastAccess",
    header: "Ultimo Acceso",
    cell: ({ row }) => fromNow(row.original.lastUsedAt),
  }),
  trustedDeviceColumnHelper.display({
    id: "browserAndOs",
    header: "Navegador / SO",
    cell: ({ row }) => deviceBrowserAndOs(row.original),
  }),
  trustedDeviceColumnHelper.display({
    id: "status",
    header: "Estado",
    cell: ({ row }) => <TrustedDeviceStatusCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: "actions",
    header: "Acciones",
    cell: ({ row }) => <TrustedDeviceRowActions device={row.original} />,
  }),
]);

interface RowDialogActionState extends DialogClosingState {
  device: TrustedDevice;
  kind: TrustedDeviceRowDialogActionKey;
}

interface TrustedDeviceTableProps {
  devices: TrustedDevice[];
  hasActiveFilters: boolean;
  onPerPageChange: (value: number) => void;
  pagination: TrustedDevicePaginationData;
}

function TrustedDeviceTable({
  devices,
  hasActiveFilters,
  onPerPageChange,
  pagination,
}: TrustedDeviceTableProps): JSX.Element {
  const table = useTable({
    columns: trustedDeviceTableColumns,
    data: devices,
    features: trustedDeviceTableFeatures,
    getRowId: (device) => String(device.id),
  });

  const tableRows = table.getRowModel().rows;

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full">
        <div className="[&>div]:max-h-140 [&>div]:min-h-130 [&>div]:rounded-md [&>div]:border">
          <Table>
            <TableHeader>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow
                  className="sticky top-0 bg-muted/70 dark:bg-muted/40"
                  key={headerGroup.id}
                >
                  {headerGroup.headers.map((header) => (
                    <TableHead key={header.id}>
                      {header.isPlaceholder ? null : <table.FlexRender header={header} />}
                    </TableHead>
                  ))}
                </TableRow>
              ))}
            </TableHeader>

            <TableBody>
              {tableRows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={trustedDeviceTableColumns.length} className="p-0">
                    <div className="flex min-h-128 flex-col items-center justify-center gap-2 py-8 text-center text-sm text-muted-foreground">
                      {hasActiveFilters ? (
                        <EmptyState
                          description="Prueba ajustar o limpiar los filtros aplicados."
                          icon={SearchX}
                          title="No se encontraron dispositivos con esos filtros."
                        />
                      ) : (
                        <EmptyState
                          icon={MonitorSmartphone}
                          title="No tienes dispositivos de confianza registrados."
                        />
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ) : (
                tableRows.map((row) => (
                  <TableRow
                    className={cn(row.original.deletedAt !== null && "opacity-75")}
                    key={row.id}
                  >
                    {row.getAllCells().map((cell) => (
                      <TableCell
                        className={getTrustedDeviceTableCellClassName(cell.column.id)}
                        key={cell.id}
                      >
                        <table.FlexRender cell={cell} />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      <TrustedDevicePagination onPerPageChange={onPerPageChange} pagination={pagination} />
    </div>
  );
}

interface TrustedDeviceDeviceCellProps {
  device: TrustedDevice;
}

function TrustedDeviceDeviceCell({ device }: TrustedDeviceDeviceCellProps): JSX.Element {
  return (
    <div className="flex items-center gap-2">
      {getDeviceIcon(device, "h-4 w-4 shrink-0 text-muted-foreground")}
      <span className="truncate">{deviceLabel(device)}</span>
    </div>
  );
}

interface TrustedDeviceStatusCellProps {
  device: TrustedDevice;
}

function TrustedDeviceStatusCell({ device }: TrustedDeviceStatusCellProps): JSX.Element {
  const { appearance } = useAppearance();

  if (device.deletedAt !== null) {
    return (
      <Badge
        variant={appearance === "light" ? "destructive" : null}
        className="rounded-md dark:bg-red-700 dark:text-white"
      >
        Revocado
      </Badge>
    );
  }

  return (
    <Badge variant={device.isActive ? "default" : "destructive"} className="rounded-md">
      {device.isActive ? "Activo" : "Expirado"}
    </Badge>
  );
}

function getTrustedDeviceTableCellClassName(columnId: string): string | undefined {
  switch (columnId) {
    case "device":
      return "font-medium";

    case "lastAccess":
    case "browserAndOs":
      return "text-muted-foreground";

    default:
      return undefined;
  }
}

interface TrustedDeviceRowActionsProps {
  device: TrustedDevice;
}

function TrustedDeviceRowActions({ device }: TrustedDeviceRowActionsProps): JSX.Element {
  const trustedDevices = usePage<SharedData & { trustedDevices: TrustedDevicePaginationData }>()
    .props.trustedDevices;

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
      trustedDevices.data.find(
        (trustedDevice) => trustedDevice.id === dialogDevice.state?.device.id,
      ) ?? null;

    if (selectedDevice === null) {
      return null;
    }

    switch (dialogDevice.state.kind) {
      case trustedDeviceRowActionKey.viewDevice:
        return (
          <TrustedDeviceDetailsDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.renameDevice:
        return (
          <TrustedDeviceRenameDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.renewTrust:
        return (
          <TrustedDeviceRenewTrustDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.revokeDevice:
        return (
          <TrustedDeviceRevokeDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.reactivate:
        return (
          <TrustedDeviceReactivationDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.forceDestroy:
        return (
          <TrustedDeviceForceDestroyDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      default:
        return null;
    }
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button size="icon" variant="ghost" className="size-8">
            <MoreHorizontalIcon />
            <span className="sr-only">Open menu</span>
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" className="w-60">
          {trustedDeviceRowActions.map((group, groupIndex) => (
            <Fragment key={groupIndex}>
              <DropdownMenuGroup>
                {group.label !== undefined && <DropdownMenuLabel>{group.label}</DropdownMenuLabel>}

                {group.actions.map((action) => {
                  const Icon = action.icon;
                  const enabled = action.isEnabled(device);

                  return (
                    <DropdownMenuItem
                      className={cn(action.className, !enabled && "cursor-not-allowed opacity-50")}
                      disabled={!enabled}
                      key={action.key}
                      onClick={(event) => {
                        if (!enabled) {
                          event.preventDefault();
                          return;
                        }

                        handleDeviceAction(action.key, device);
                      }}
                    >
                      <Icon
                        className={cn(
                          action.iconClassName ?? "text-muted-foreground",
                          !enabled && "opacity-70",
                        )}
                      />
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

      {renderDialogDevice()}
    </>
  );
}

export default TrustedDeviceTable;
