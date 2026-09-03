import type { JSX } from "react";
import { Fragment } from "react";

import { usePage } from "@inertiajs/react";

import { MonitorSmartphone, MoreHorizontalIcon } from "lucide-react";

import {
  TrustedDeviceForceDestroyDialog,
  TrustedDeviceReactivationDialog,
} from "@/modules/setting/modules/trustedDevices/components/dialog";
import TrustedDevicesPagination from "@/modules/setting/modules/trustedDevices/components/table/TrustedDevicesPagination";
import {
  trustedDeviceRevokedRowActionKey,
  type TrustedDeviceRevokedRowActionKey,
  trustedDeviceRevokedRowActions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";
import type {
  TrustedDevice,
  TrustedDevicePagination,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import EmptyState from "@/modules/setting/shared/components/EmptyState";
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

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";

interface TrustedDevicesRevokedTableProps {
  devices: TrustedDevice[];
  pagination: TrustedDevicePagination;
}

interface RevokedRowDialogState extends DialogClosingState {
  device: TrustedDevice;
  kind: TrustedDeviceRevokedRowActionKey;
}

interface RevokedTableColumn {
  label: "Dispositivo" | "Revocado" | "Navegador / SO" | "Acciones";
}

function TrustedDevicesRevokedTable({
  devices,
  pagination,
}: TrustedDevicesRevokedTableProps): JSX.Element {
  const revokedTableColumns: readonly RevokedTableColumn[] = [
    { label: "Dispositivo" },
    { label: "Revocado" },
    { label: "Navegador / SO" },
    { label: "Acciones" },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="[&>div]:max-h-140 [&>div]:min-h-130 [&>div]:rounded-md [&>div]:border">
        <Table>
          <TableHeader>
            <TableRow className="sticky top-0 bg-muted/70 dark:bg-muted/40">
              {revokedTableColumns.map((column) => (
                <TableHead key={column.label}>{column.label}</TableHead>
              ))}
            </TableRow>
          </TableHeader>

          <TableBody>
            {devices.length === 0 ? (
              <TableRow>
                <TableCell colSpan={revokedTableColumns.length} className="p-0">
                  <EmptyState icon={MonitorSmartphone} title="No tienes dispositivos revocados." />
                </TableCell>
              </TableRow>
            ) : (
              devices.map((device) => <TrustedDeviceRevokedRow device={device} key={device.id} />)
            )}
          </TableBody>
        </Table>
      </div>

      <TrustedDevicesPagination onPerPageChange={() => undefined} pagination={pagination} />
    </div>
  );
}

interface TrustedDeviceRevokedRowProps {
  device: TrustedDevice;
}

function TrustedDeviceRevokedRow({ device }: TrustedDeviceRevokedRowProps): JSX.Element {
  const revokedDevices = usePage<{ revokedDevices: TrustedDevicePagination }>().props
    .revokedDevices;

  const browserAndOs = deviceBrowserAndOs(device);

  const rowDialog = useDialog<RevokedRowDialogState | null>(null);

  const handleAction = (kind: TrustedDeviceRevokedRowActionKey): void => {
    rowDialog.show({ kind, device, closing: false });
  };

  const handleClose = createDialogCloseHandler(rowDialog);

  const selectedDevice =
    revokedDevices.data.find((candidate) => candidate.id === rowDialog.state?.device.id) ?? null;

  const renderDialog = (): JSX.Element | null => {
    if (rowDialog.state === null || selectedDevice === null) {
      return null;
    }

    if (rowDialog.state.kind === trustedDeviceRevokedRowActionKey.reactivate) {
      return (
        <TrustedDeviceReactivationDialog
          device={selectedDevice}
          onClose={handleClose}
          open={!rowDialog.state.closing}
        />
      );
    }

    return (
      <TrustedDeviceForceDestroyDialog
        device={selectedDevice}
        onClose={handleClose}
        open={!rowDialog.state.closing}
      />
    );
  };

  return (
    <TableRow className={cn(device.deletedAt !== null && "opacity-75")}>
      <TableCell className="font-medium">
        <div className="flex items-center gap-2">
          {getDeviceIcon(device, "h-4 w-4 shrink-0 text-muted-foreground")}
          <span className="truncate">{deviceLabel(device)}</span>
        </div>
      </TableCell>

      <TableCell>
        <Badge variant="destructive" className="rounded-md">
          Revocado
        </Badge>
      </TableCell>

      <TableCell className="text-muted-foreground">{browserAndOs}</TableCell>

      <TableCell>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button size="icon" variant="ghost" className="size-8">
              <MoreHorizontalIcon />
              <span className="sr-only">Open menu</span>
            </Button>
          </DropdownMenuTrigger>

          <DropdownMenuContent align="end" className="w-56">
            {trustedDeviceRevokedRowActions.map((group, groupIndex) => (
              <Fragment key={groupIndex}>
                <DropdownMenuGroup>
                  {group.actions.map((action) => {
                    const Icon = action.icon;

                    return (
                      <DropdownMenuItem
                        className={action.className}
                        key={action.key}
                        onClick={() => {
                          handleAction(action.key);
                        }}
                      >
                        <Icon className={action.iconClassName ?? "text-muted-foreground"} />
                        {action.label}
                      </DropdownMenuItem>
                    );
                  })}
                </DropdownMenuGroup>

                {groupIndex < trustedDeviceRevokedRowActions.length - 1 && (
                  <DropdownMenuSeparator />
                )}
              </Fragment>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        {renderDialog()}
      </TableCell>
    </TableRow>
  );
}

export default TrustedDevicesRevokedTable;
