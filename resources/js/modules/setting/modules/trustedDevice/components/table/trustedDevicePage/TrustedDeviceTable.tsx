import type { JSX } from "react";
import { Fragment, useRef } from "react";

import { useHotkeySequences } from "@tanstack/react-hotkeys";
import {
  columnFilteringFeature,
  columnResizingFeature,
  columnSizingFeature,
  columnVisibilityFeature,
  type ColumnVisibilityState,
  createColumnHelper,
  type OnChangeFn,
  rowPaginationFeature,
  rowSelectionFeature,
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
import { defaultTrustedDevicePerPage } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import {
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import {
  trustedDeviceTableColumnIds,
  trustedDeviceTableColumnSizes,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns";
import { createTrustedDeviceShortcutHandler } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceShortcuts";
import type {
  TrustedDevice,
  TrustedDevicePagination as TrustedDevicePaginationData,
} from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
import { formatLongDate, formatTimeUntil, fromNow } from "@/modules/setting/shared/utils/dateTime";
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
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/shared/components/shadcn/ui/table";

import { useAppearance, useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { badgeVariants } from "@/shared/lib/styling";

type TrustedDeviceRowDialogActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

interface TrustedDeviceTableMeta {
  onDeviceAction: (
    action: TrustedDeviceRowDialogActionKey,
    device: TrustedDevice,
    returnFocusTarget?: HTMLElement | null,
  ) => void;
}

interface RowDialogActionState extends DialogClosingState {
  deviceId: TrustedDevice["id"];
  kind: TrustedDeviceRowDialogActionKey;
  returnFocusTarget: HTMLElement | null;
}

const trustedDeviceTableFeatures = tableFeatures({
  columnFilteringFeature,
  columnSizingFeature,
  columnResizingFeature,
  columnVisibilityFeature,
  rowPaginationFeature,
  rowSelectionFeature,
  rowSortingFeature,
  tableMeta: {} as TrustedDeviceTableMeta,
});

const trustedDeviceColumnHelper = createColumnHelper<
  typeof trustedDeviceTableFeatures,
  TrustedDevice
>();

const trustedDeviceTableColumns = trustedDeviceColumnHelper.columns([
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.device,
    header: "Dispositivo",
    size: trustedDeviceTableColumnSizes.device,
    minSize: 150,
    maxSize: 360,
    enableHiding: false,
    cell: ({ row }) => <TrustedDeviceDeviceCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.lastAccess,
    header: "Último acceso",
    size: trustedDeviceTableColumnSizes.lastAccess,
    minSize: 120,
    maxSize: 280,
    cell: ({ row }) => fromNow(row.original.lastUsedAt),
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.browserAndOs,
    header: "Navegador / SO",
    size: trustedDeviceTableColumnSizes.browserAndOs,
    minSize: 140,
    maxSize: 320,
    cell: ({ row }) => deviceBrowserAndOs(row.original),
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.ip,
    header: "IP",
    size: trustedDeviceTableColumnSizes.ip,
    minSize: 120,
    maxSize: 260,
    cell: ({ row }) => <TrustedDeviceIpCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.expiration,
    header: "Expira",
    size: trustedDeviceTableColumnSizes.expiration,
    minSize: 120,
    maxSize: 240,
    cell: ({ row }) => <TrustedDeviceExpirationCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.status,
    header: "Estado",
    size: trustedDeviceTableColumnSizes.status,
    minSize: 90,
    maxSize: 180,
    cell: ({ row }) => <TrustedDeviceStatusCell device={row.original} />,
  }),
  trustedDeviceColumnHelper.display({
    id: trustedDeviceTableColumnIds.actions,
    header: "Acciones",
    size: trustedDeviceTableColumnSizes.actions,
    minSize: 96,
    maxSize: 180,
    enableHiding: false,
    cell: ({ row, table }) => {
      const onDeviceAction = table.options.meta?.onDeviceAction;

      if (onDeviceAction === undefined) {
        throw new Error(
          "Trusted device row actions require an onDeviceAction table metadata handler.",
        );
      }

      return <TrustedDeviceRowActions device={row.original} onDeviceAction={onDeviceAction} />;
    },
  }),
]);

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
  const deviceDialog = useDialog<RowDialogActionState | null>(null);

  const handleDeviceAction = (
    action: TrustedDeviceRowDialogActionKey,
    device: TrustedDevice,
    returnFocusTarget?: HTMLElement | null,
  ): void => {
    const activeElement = document.activeElement;
    const focusTarget =
      returnFocusTarget ??
      (activeElement instanceof HTMLElement && activeElement !== document.body
        ? activeElement
        : null);

    deviceDialog.show({
      kind: action,
      deviceId: device.id,
      returnFocusTarget: focusTarget,
      closing: false,
    });
  };

  const handleDialogClose = createDialogCloseHandler(deviceDialog);

  const table = useTable({
    columns: trustedDeviceTableColumns,
    data: devices,
    features: trustedDeviceTableFeatures,
    getRowId: (device) => String(device.id),
    key: "trusted-devices",
    manualFiltering: true,
    manualPagination: true,
    manualSorting: true,
    enableMultiRowSelection: false,
    enableRowRangeSelection: false,
    columnResizeMode: "onChange",
    rowCount: pagination.total,
    onColumnVisibilityChange,
    meta: { onDeviceAction: handleDeviceAction },
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
  const isCompactPageSize = pagination.per_page < defaultTrustedDevicePerPage;
  const selectedDevice = table.getSelectedRowModel().rows[0]?.original ?? null;

  const rowActionShortcutDefinitions = trustedDeviceRowActions
    .flatMap((group) => group.actions)
    .map((action) => ({
      sequence: [...action.shortcut],
      callback: createTrustedDeviceShortcutHandler(() => {
        if (selectedDevice !== null) {
          handleDeviceAction(action.key, selectedDevice);
        }
      }),
      options: {
        enabled:
          deviceDialog.state === null &&
          selectedDevice !== null &&
          action.isEnabled(selectedDevice),
      },
    }));

  useHotkeySequences(rowActionShortcutDefinitions, {
    enabled: true,
    ignoreInputs: true,
    preventDefault: true,
    stopPropagation: false,
  });

  const renderDialogDevice = (): JSX.Element | null => {
    const currentDialog = deviceDialog.state;

    if (currentDialog === null) {
      return null;
    }

    const isClosing = currentDialog.closing;
    const dialogDevice = devices.find((device) => device.id === currentDialog.deviceId) ?? null;

    if (dialogDevice === null) {
      return null;
    }

    switch (currentDialog.kind) {
      case trustedDeviceRowActionKey.viewDevice:
        return (
          <TrustedDeviceDetailsDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
            returnFocusTarget={currentDialog.returnFocusTarget}
          />
        );

      case trustedDeviceRowActionKey.renameDevice:
        return (
          <TrustedDeviceRenameDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
            returnFocusTarget={currentDialog.returnFocusTarget}
          />
        );

      case trustedDeviceRowActionKey.renewTrust:
        return (
          <TrustedDeviceRenewTrustDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.revokeDevice:
        return (
          <TrustedDeviceRevokeDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.reactivate:
        return (
          <TrustedDeviceReactivationDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.forceDestroy:
        return (
          <TrustedDeviceForceDestroyDialog
            device={dialogDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      default:
        return null;
    }
  };

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <div className="w-full min-w-0">
        <div
          className={cn(
            "[&>div]:w-full [&>div]:min-w-0 [&>div]:rounded-md [&>div]:border",
            isCompactPageSize
              ? "[&>div]:max-h-none [&>div]:min-h-0"
              : "[&>div]:max-h-140 [&>div]:min-h-130",
          )}
        >
          <Table className="table-fixed" style={{ width: `max(100%, ${table.getTotalSize()}px)` }}>
            <TableCaption className="sr-only">Dispositivos de confianza registrados</TableCaption>
            <TableHeader>
              {table.getHeaderGroups().map((headerGroup) => (
                <TableRow
                  className="sticky top-0 z-10 bg-muted hover:bg-muted"
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
                        scope="col"
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
                    <div
                      className={cn(
                        "flex flex-col items-center justify-center gap-2 py-8 text-center text-sm text-muted-foreground",
                        isCompactPageSize ? "min-h-48" : "min-h-128",
                      )}
                    >
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
                    aria-selected={row.getIsSelected()}
                    className={cn(row.original.deletedAt !== null && "opacity-75")}
                    data-state={row.getIsSelected() ? "selected" : undefined}
                    key={row.id}
                    onClick={() => {
                      row.toggleSelected(true);
                    }}
                    onFocusCapture={() => {
                      row.toggleSelected(true);
                    }}
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
      {renderDialogDevice()}
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
  const { resolvedAppearance } = useAppearance();

  if (device.deletedAt !== null) {
    return (
      <Badge
        variant={resolvedAppearance === "light" ? "destructive" : null}
        className="rounded-md dark:bg-red-700 dark:text-white"
      >
        Revocado
      </Badge>
    );
  }

  return (
    <Badge
      className={cn(device.isActive ? badgeVariants.success : badgeVariants.warning, "rounded-md")}
    >
      {device.isActive ? "Activo" : "Expirado"}
    </Badge>
  );
}

interface TrustedDeviceIpCellProps {
  device: TrustedDevice;
}

function TrustedDeviceIpCell({ device }: TrustedDeviceIpCellProps): JSX.Element {
  const ipAddress = device.ip ?? "No disponible";

  return (
    <span className="block truncate font-mono text-xs" title={device.ip ?? undefined}>
      {ipAddress}
    </span>
  );
}

interface TrustedDeviceExpirationCellProps {
  device: TrustedDevice;
}

function TrustedDeviceExpirationCell({ device }: TrustedDeviceExpirationCellProps): JSX.Element {
  const isRevoked = device.deletedAt !== null;

  return (
    <span title={isRevoked ? undefined : formatLongDate(device.expiresAt)}>
      {isRevoked ? "No aplica" : formatTimeUntil(device.expiresAt)}
    </span>
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
  onDeviceAction: (
    action: TrustedDeviceRowDialogActionKey,
    device: TrustedDevice,
    returnFocusTarget?: HTMLElement | null,
  ) => void;
}

function TrustedDeviceRowActions({
  device,
  onDeviceAction,
}: TrustedDeviceRowActionsProps): JSX.Element {
  const actionTriggerRef = useRef<HTMLButtonElement | null>(null);

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            aria-label={`Abrir acciones de ${deviceLabel(device)}`}
            size="icon"
            variant="ghost"
            className="size-8"
            ref={actionTriggerRef}
          >
            <MoreHorizontalIcon />
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

                        onDeviceAction(action.key, device, actionTriggerRef.current);
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
    </>
  );
}

export default TrustedDeviceTable;
