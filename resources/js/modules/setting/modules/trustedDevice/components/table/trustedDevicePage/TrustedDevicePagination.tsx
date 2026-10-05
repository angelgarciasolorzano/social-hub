import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ChevronLeft, ChevronRight } from "lucide-react";

import { trustedDevicePerPageOptions } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import type { TrustedDevicePagination } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";
import {
  buildPageUrl,
  computePaginationRange,
} from "@/modules/setting/modules/trustedDevice/utils/pagination";

import { buttonVariants } from "@/shared/components/shadcn/ui/button";
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

import { cn } from "@/shared/lib";

interface TrustedDevicePaginationProps {
  onPerPageChange: (value: number) => void;
  pagination: TrustedDevicePagination;
}

function TrustedDevicePagination({
  onPerPageChange,
  pagination,
}: TrustedDevicePaginationProps): JSX.Element {
  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <div className="grid min-w-0 grid-cols-1 items-center gap-3 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
      <span className="text-sm text-muted-foreground">
        Página {pagination.current_page} de {pagination.last_page}
      </span>

      <Pagination aria-label="Paginación de dispositivos de confianza" className="min-w-0">
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

      <div className="flex items-center justify-between gap-3 sm:justify-self-end">
        <Label className="text-sm whitespace-nowrap" htmlFor="trusted-devices-per-page">
          Filas por página
        </Label>

        <Select
          value={String(pagination.per_page)}
          onValueChange={(value) => {
            onPerPageChange(Number.parseInt(value, 10));
          }}
        >
          <SelectTrigger className="w-20" id="trusted-devices-per-page">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              {trustedDevicePerPageOptions.map((option) => (
                <SelectItem key={option} value={String(option)}>
                  {String(option)}
                </SelectItem>
              ))}
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
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
      only={["trustedDevices"]}
      preserveScroll
      preserveState
    >
      {page}
    </Link>
  );
}

type PaginationPreviousLinkProps = Pick<PaginationNumberLinkProps, "pagination">;

function PaginationPreviousLink({ pagination }: PaginationPreviousLinkProps): JSX.Element {
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
      only={["trustedDevices"]}
      preserveScroll
      preserveState
    >
      <ChevronLeft className="size-4" />
    </Link>
  );
}

type PaginationNextLinkProps = PaginationPreviousLinkProps;

function PaginationNextLink({ pagination }: PaginationNextLinkProps): JSX.Element {
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
      only={["trustedDevices"]}
      preserveScroll
      preserveState
    >
      <ChevronRight className="size-4" />
    </Link>
  );
}

export default TrustedDevicePagination;
