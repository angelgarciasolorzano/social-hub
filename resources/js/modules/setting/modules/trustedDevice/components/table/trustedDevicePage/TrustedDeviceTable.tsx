import type { JSX } from "react";
import { Fragment } from "react";

import { usePage } from "@inertiajs/react";

import {
  columnFilteringFeature,
  columnResizingFeature,
  columnSizingFeature,
  columnVisibilityFeature,
  type ColumnVisibilityState,
  createColumnHelper,
  type OnChangeFn,
  rowPaginationFeature,
  rowSortingFeature,
  tableFeatures,
  useTable,
} from "@tanstack/react-table";
import { useTanStackTableDevtools } from "@tanstack/react-table-devtools";
import { MonitorSmartphone, MoreHorizontalIcon, SearchX } from "lucide-react";

import {
  TrustedDeviceDetailsDialog,
  TrustedDeviceForceDestroyDialog,
  TrustedDeviceReactivationDialog,
  TrustedDeviceRenameDialog,
  TrustedDeviceRenewTrustDialog,
  TrustedDeviceRevokeDialog,
} from "@/modules/setting/modules/trustedDevice/components/dialog";
import TrustedDeviceColumnResizeHandle from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceColumnResizeHandle";
import TrustedDevicePagination from "@/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDevicePagination";
import {
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import { trustedDeviceTableColumnIds } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns";
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

const trustedDeviceTableFeatures = tableFeatures({
  columnFilteringFeature,
  columnSizingFeature,
  columnResizingFeature,
  columnVisibilityFeature,
  rowPaginationFeature,
  rowSortingFeature,
});

const trustedDeviceColumnHelper = createColumnHelper<
  typeof trustedDeviceTableFeatures,
  TrustedDevice
>();

const trustedDeviceTableColumns = trustedDeviceColumnHelper.columns([
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.device,
    header: "Dispositivo",
    size: 180,
    minSize: 150,
    maxSize: 360,
    enableHiding: false,
    cell: ({ row }) => <TrustedDeviceDeviceCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.lastAccess,
    header: "Último acceso",
    size: 140,
    minSize: 120,
    maxSize: 280,
    cell: ({ row }) => fromNow(row.original.lastUsedAt),
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.browserAndOs,
    header: "Navegador / SO",
    size: 165,
    minSize: 140,
    maxSize: 320,
    cell: ({ row }) => deviceBrowserAndOs(row.original),
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.status,
    header: "Estado",
    size: 100,
    minSize: 90,
    maxSize: 180,
    cell: ({ row }) => <TrustedDeviceStatusCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.actions,
    header: "Acciones",
    size: 115,
    minSize: 96,
    maxSize: 180,
    enableHiding: false,
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
  columnVisibility: ColumnVisibilityState;
  onColumnVisibilityChange: OnChangeFn<ColumnVisibilityState>;
  onPerPageChange: (value: number) => void;
  pagination: TrustedDevicePaginationData;
}

function TrustedDeviceTable({
  devices,
  hasActiveFilters,
  columnVisibility,
  onColumnVisibilityChange,
  onPerPageChange,
  pagination,
}: TrustedDeviceTableProps): JSX.Element {
  const table = useTable({
    columns: trustedDeviceTableColumns,
    data: devices,
    features: trustedDeviceTableFeatures,
    getRowId: (device) => String(device.id),
    key: "trusted-devices",
    manualFiltering: true,
    manualPagination: true,
    manualSorting: true,
    columnResizeMode: "onChange",
    rowCount: pagination.total,
    onColumnVisibilityChange,
    state: {
      columnVisibility,
      pagination: {
        pageIndex: pagination.current_page - 1,
        pageSize: pagination.per_page,
      },
    },
  });

  useTanStackTableDevtools(table);

  const tableRows = table.getRowModel().rows;

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <div className="w-full min-w-0">
        <div className="[&>div]:max-h-140 [&>div]:min-h-130 [&>div]:w-full [&>div]:min-w-0 [&>div]:rounded-md [&>div]:border">
          <Table className="table-fixed" style={{ width: `max(100%, ${table.getTotalSize()}px)` }}>
            <TableHeader>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow
                  className="sticky top-0 bg-muted/30 hover:bg-muted/30"
                  key={headerGroup.id}
                >
                  {headerGroup.headers.map((header) => {
                    const headerLabel =
                      typeof header.column.columnDef.header === "string"
                        ? header.column.columnDef.header
                        : header.column.id;

                    return (
                      <TableHead
                        className="group relative overflow-hidden"
                        key={header.id}
                        style={{ width: header.getSize() }}
                      >
                        {header.isPlaceholder ? null : <table.FlexRender header={header} />}

                        {header.column.getCanResize() && (
                          <TrustedDeviceColumnResizeHandle
                            ariaLabel={`Redimensionar columna ${headerLabel}`}
                            isResizing={header.column.getIsResizing()}
                            maximumSize={header.column.columnDef.maxSize ?? Number.MAX_SAFE_INTEGER}
                            minimumSize={header.column.columnDef.minSize ?? 20}
                            onMouseDown={header.getResizeHandler()}
                            onResize={(nextSize) => {
                              table.setColumnSizing((columnSizing) => ({
                                ...columnSizing,
                                [header.column.id]: nextSize,
                              }));
                            }}
                            onTouchStart={header.getResizeHandler()}
                            resizeDirection={table.options.columnResizeDirection ?? "ltr"}
                            size={header.getSize()}
                          />
                        )}
                      </TableHead>
                    );
                  })}
                  <TableHead aria-hidden="true" className="p-0" />
                </TableRow>
              ))}
            </TableHeader>

            <TableBody>
              {tableRows.length === 0 ? (
                <TableRow>
                  <TableCell className="p-0" colSpan={table.getVisibleLeafColumns().length + 1}>
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
                    {row.getVisibleCells().map((cell) => (
                      <TableCell
                        className={cn(
                          "overflow-hidden",
                          getTrustedDeviceTableCellClassName(cell.column.id),
                        )}
                        key={cell.id}
                        style={{ width: cell.column.getSize() }}
                      >
                        <table.FlexRender cell={cell} />
                      </TableCell>
                    ))}
                    <TableCell aria-hidden="true" className="p-0" />
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
    case trustedDeviceTableColumnIds.device:
      return "font-medium";

    case trustedDeviceTableColumnIds.lastAccess:
    case trustedDeviceTableColumnIds.browserAndOs:
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
