import type { JSX } from "react";
import { Fragment } from "react";

import { Link, usePage } from "@inertiajs/react";

import { ChevronLeft, ChevronRight, MonitorSmartphone, MoreHorizontalIcon } from "lucide-react";

import {
  DeviceDetailsDialog,
  RenameDeviceDialog,
  RenewTrustDialog,
  RevokeDeviceDialog,
} from "@/modules/setting/modules/trustedDevices/components/dialog";
import {
  trustedDeviceRowActionKey,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";
import type {
  TrustedDevice,
  TrustedDevicePagination,
} from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
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

import { useDialog } from "@/shared/hooks";

import type { SharedData } from "@/shared/types";

type TrustedDeviceRowDialogActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

interface RowDialogActionState extends DialogClosingState {
  device: TrustedDevice;
  kind: TrustedDeviceRowDialogActionKey;
}

interface TrustedDevicesTableProps {
  devices: TrustedDevice[];
  pagination: TrustedDevicePagination;
}

function TrustedDevicesTable({ devices, pagination }: TrustedDevicesTableProps): JSX.Element {
  return (
    <div className="flex flex-col gap-4">
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
              {devices.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="p-0">
                    <EmptyState
                      icon={MonitorSmartphone}
                      title="No tienes dispositivos de confianza registrados."
                    />
                  </TableCell>
                </TableRow>
              ) : (
                devices.map((device) => <TrustedDeviceRow device={device} key={device.id} />)
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">
            Mostrando {pagination.from}–{pagination.to} de {pagination.total}
          </span>
          <div className="flex items-center gap-2">
            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={pagination.prev_page_url === null}
            >
              <Link href={pagination.prev_page_url ?? ""} preserveScroll>
                <ChevronLeft />
                Anterior
              </Link>
            </Button>

            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={pagination.next_page_url === null}
            >
              <Link href={pagination.next_page_url ?? ""} preserveScroll>
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
  const trustedDevices = usePage<SharedData & { trustedDevices: TrustedDevicePagination }>().props
    .trustedDevices;

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
      trustedDevices.data.find((d) => d.id === dialogDevice.state?.device.id) ?? null;

    if (selectedDevice === null) {
      return null;
    }

    switch (dialogDevice.state.kind) {
      case trustedDeviceRowActionKey.viewDevice:
        return (
          <DeviceDetailsDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.renameDevice:
        return (
          <RenameDeviceDialog
            device={selectedDevice}
            onClose={handleDialogClose}
            open={!isClosing}
          />
        );

      case trustedDeviceRowActionKey.renewTrust:
        return (
          <RenewTrustDialog device={selectedDevice} onClose={handleDialogClose} open={!isClosing} />
        );

      case trustedDeviceRowActionKey.revokeDevice:
        return (
          <RevokeDeviceDialog
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
            <Button size="icon" variant="ghost" className="size-8">
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
                        className={action.className}
                        key={action.key}
                        onClick={() => {
                          handleDeviceAction(action.key, device);
                        }}
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

export default TrustedDevicesTable;
