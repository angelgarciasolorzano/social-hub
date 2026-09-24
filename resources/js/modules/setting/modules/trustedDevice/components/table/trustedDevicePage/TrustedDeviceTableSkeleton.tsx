import type { JSX } from "react";

import type { ColumnVisibilityState } from "@tanstack/react-table";

import {
  trustedDeviceTableColumnIds,
  trustedDeviceTableColumnSizes,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns";

import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/shared/components/shadcn/ui/table";

interface TrustedDeviceTableSkeletonProps {
  columnVisibility: ColumnVisibilityState;
  hasActiveFilters: boolean;
}

const trustedDeviceSkeletonColumns = [
  {
    id: trustedDeviceTableColumnIds.device,
    label: "Dispositivo",
    width: trustedDeviceTableColumnSizes.device,
    kind: "device",
  },
  {
    id: trustedDeviceTableColumnIds.lastAccess,
    label: "Último acceso",
    width: trustedDeviceTableColumnSizes.lastAccess,
    kind: "text",
  },
  {
    id: trustedDeviceTableColumnIds.browserAndOs,
    label: "Navegador / SO",
    width: trustedDeviceTableColumnSizes.browserAndOs,
    kind: "text",
  },
  {
    id: trustedDeviceTableColumnIds.ip,
    label: "IP",
    width: trustedDeviceTableColumnSizes.ip,
    kind: "text",
  },
  {
    id: trustedDeviceTableColumnIds.expiration,
    label: "Expira",
    width: trustedDeviceTableColumnSizes.expiration,
    kind: "text",
  },
  {
    id: trustedDeviceTableColumnIds.status,
    label: "Estado",
    width: trustedDeviceTableColumnSizes.status,
    kind: "status",
  },
  {
    id: trustedDeviceTableColumnIds.actions,
    label: "Acciones",
    width: trustedDeviceTableColumnSizes.actions,
    kind: "actions",
  },
] as const;

const trustedDeviceSkeletonRowIndexes = Array.from({ length: 12 }, (_, rowIndex) => rowIndex);
const trustedDeviceSkeletonWidths = ["w-1/2", "w-3/5", "w-2/3", "w-4/5"] as const;

function TrustedDeviceTableSkeleton({
  columnVisibility,
  hasActiveFilters,
}: TrustedDeviceTableSkeletonProps): JSX.Element {
  const visibleColumns = trustedDeviceSkeletonColumns.filter(
    ({ id }) => columnVisibility[id] !== false,
  );

  const tableWidth = visibleColumns.reduce((totalWidth, column) => totalWidth + column.width, 0);

  return (
    <div aria-busy="true" className="flex min-w-0 flex-col gap-4">
      <span className="sr-only" role="status">
        Cargando dispositivos de confianza
      </span>

      <div aria-hidden="true" className="flex flex-col gap-3">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
          <Skeleton className="h-10 w-full max-w-xs xl:min-w-0 xl:flex-1" />

          <div className="flex flex-wrap items-center justify-start gap-2 xl:flex-nowrap xl:justify-center">
            <Skeleton className="h-10 w-24" />
            <Skeleton className="h-10 w-36" />
            <Skeleton className="h-10 w-28" />
            <Skeleton className="size-10 shrink-0" />
          </div>
        </div>

        {hasActiveFilters && (
          <div className="flex flex-wrap items-center gap-2">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-7 w-28 rounded-full" />
            <Skeleton className="h-7 w-32 rounded-full" />
          </div>
        )}
      </div>

      <div className="w-full min-w-0" aria-hidden="true">
        <div className="[&>div]:max-h-140 [&>div]:min-h-130 [&>div]:w-full [&>div]:min-w-0 [&>div]:rounded-md [&>div]:border">
          <Table className="table-fixed" style={{ width: `max(100%, ${tableWidth}px)` }}>
            <TableHeader>
              <TableRow className="sticky top-0 z-10 bg-muted hover:bg-muted">
                {visibleColumns.map((column) => (
                  <TableHead key={column.id} style={{ width: column.width }}>
                    {column.label}
                  </TableHead>
                ))}
                <TableHead className="p-0" />
              </TableRow>
            </TableHeader>

            <TableBody>
              {trustedDeviceSkeletonRowIndexes.map((rowIndex) => (
                <TableRow className="h-10 hover:bg-transparent" key={rowIndex}>
                  {visibleColumns.map((column, columnIndex) => (
                    <TableCell key={`${column.id}-${rowIndex}`} style={{ width: column.width }}>
                      <TrustedDeviceSkeletonCell
                        column={column}
                        columnIndex={columnIndex}
                        rowIndex={rowIndex}
                      />
                    </TableCell>
                  ))}
                  <TableCell className="p-0" />
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>

      <div className="flex items-center justify-between gap-4" aria-hidden="true">
        <Skeleton className="h-4 w-24" />

        <div className="flex flex-1 items-center justify-center gap-2">
          <Skeleton className="size-10" />
          <Skeleton className="size-10" />
          <Skeleton className="size-10" />
        </div>

        <div className="flex items-center gap-3">
          <Skeleton className="hidden h-4 w-28 sm:block" />
          <Skeleton className="h-10 w-32" />
        </div>
      </div>
    </div>
  );
}

interface TrustedDeviceSkeletonCellProps {
  column: (typeof trustedDeviceSkeletonColumns)[number];
  columnIndex: number;
  rowIndex: number;
}

function TrustedDeviceSkeletonCell({
  column,
  columnIndex,
  rowIndex,
}: TrustedDeviceSkeletonCellProps): JSX.Element {
  if (column.kind === "device") {
    return (
      <div className="flex items-center gap-2">
        <Skeleton className="size-4 shrink-0 rounded-sm" />
        <Skeleton className="h-4 w-3/5" />
      </div>
    );
  }

  if (column.kind === "status") {
    return <Skeleton className="h-6 w-16 rounded-md" />;
  }

  if (column.kind === "actions") {
    return (
      <div className="flex justify-center">
        <Skeleton className="size-7 rounded-md" />
      </div>
    );
  }

  const width =
    trustedDeviceSkeletonWidths[(rowIndex + columnIndex) % trustedDeviceSkeletonWidths.length] ??
    "w-1/2";

  return <Skeleton className={`h-4 ${width}`} />;
}

export default TrustedDeviceTableSkeleton;
