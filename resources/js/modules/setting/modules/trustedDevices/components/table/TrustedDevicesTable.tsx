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
import { Button, buttonVariants } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";
import { Label } from "@/shared/components/shadcn/ui/label";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/shared/components/shadcn/ui/select";
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

      <TrustedDevicesPagination pagination={pagination} />
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

interface TrustedDevicesPaginationProps {
  pagination: TrustedDevicePagination;
}

const pageSizeItems = [
  { label: "5", value: "5" },
  { label: "10", value: "10" },
  { label: "15", value: "15" },
  { label: "25", value: "25" },
  { label: "50", value: "50" },
];

function TrustedDevicesPagination({ pagination }: TrustedDevicesPaginationProps): JSX.Element {
  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-sm text-muted-foreground">
        Pagina {pagination.current_page} de {pagination.last_page}
      </span>

      <Pagination className="flex-1">
        <PaginationContent>
          <PaginationItem>
            <PaginationPreviousLink pagination={pagination} />
          </PaginationItem>

          {pages.map((page, index) =>
            page === "ellipsis" ? (
              <PaginationItem key={`ellipsis-${index}`}>
                <PaginationEllipsis />
              </PaginationItem>
            ) : (
              <PaginationItem key={page}>
                <PaginationNumberLink page={page} pagination={pagination} />
              </PaginationItem>
            ),
          )}

          <PaginationItem>
            <PaginationNextLink pagination={pagination} />
          </PaginationItem>
        </PaginationContent>
      </Pagination>

      <div className="flex items-center gap-3">
        <Label className="hidden whitespace-nowrap sm:block">Filas por pagina</Label>

        <Select defaultValue={String(pagination.per_page)}>
          <SelectTrigger className="w-full max-w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              {pageSizeItems.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}

function computePaginationRange(currentPage: number, lastPage: number): (number | "ellipsis")[] {
  const visiblePages = 5;
  const half = Math.floor(visiblePages / 2);

  if (lastPage <= visiblePages) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  const start = Math.max(2, currentPage - half);
  const end = Math.min(lastPage - 1, currentPage + half);

  const pages: (number | "ellipsis")[] = [];

  if (start > 2) {
    pages.push("ellipsis");
  }

  for (let page = start; page <= end; page += 1) {
    pages.push(page);
  }

  if (end < lastPage - 1) {
    pages.push("ellipsis");
  }

  return pages;
}

interface PaginationNumberLinkProps {
  page: number;
  pagination: TrustedDevicePagination;
}

function PaginationNumberLink({ page, pagination }: PaginationNumberLinkProps): JSX.Element {
  const isActive = page === pagination.current_page;
  const url = buildPageUrl(pagination, page);

  if (url === null) {
    return (
      <span
        aria-current={isActive ? "page" : undefined}
        className={cn(buttonVariants({ variant: isActive ? "outline" : "ghost", size: "icon" }))}
      >
        {page}
      </span>
    );
  }

  return (
    <Link
      aria-current={isActive ? "page" : undefined}
      className={cn(buttonVariants({ variant: isActive ? "outline" : "ghost", size: "icon" }))}
      href={url}
      preserveScroll
    >
      {page}
    </Link>
  );
}

interface PaginationPrevNextLinkProps {
  pagination: TrustedDevicePagination;
}

function PaginationPreviousLink({ pagination }: PaginationPrevNextLinkProps): JSX.Element {
  const url = pagination.prev_page_url;

  if (url === null) {
    return (
      <span
        aria-disabled
        aria-label="Pagina anterior"
        className={cn(
          buttonVariants({ variant: "outline", size: "icon" }),
          "pointer-events-none opacity-50",
        )}
      >
        <ChevronLeft className="size-4" />
      </span>
    );
  }

  return (
    <Link
      aria-label="Pagina anterior"
      className={cn(buttonVariants({ variant: "outline", size: "icon" }))}
      href={url}
      preserveScroll
    >
      <ChevronLeft className="size-4" />
    </Link>
  );
}

function PaginationNextLink({ pagination }: PaginationPrevNextLinkProps): JSX.Element {
  const url = pagination.next_page_url;

  if (url === null) {
    return (
      <span
        aria-disabled
        aria-label="Pagina siguiente"
        className={cn(
          buttonVariants({ variant: "outline", size: "icon" }),
          "pointer-events-none opacity-50",
        )}
      >
        <ChevronRight className="size-4" />
      </span>
    );
  }

  return (
    <Link
      aria-label="Pagina siguiente"
      className={cn(buttonVariants({ variant: "outline", size: "icon" }))}
      href={url}
      preserveScroll
    >
      <ChevronRight className="size-4" />
    </Link>
  );
}

function buildPageUrl(pagination: TrustedDevicePagination, page: number): string | null {
  const link = pagination.links.find((candidate) => candidate.page === page);

  return link?.url ?? null;
}
