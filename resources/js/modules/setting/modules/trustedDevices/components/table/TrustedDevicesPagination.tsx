import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ChevronLeft, ChevronRight } from "lucide-react";

import { trustedDevicePerPageOptions } from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import type { TrustedDevicePagination } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";
import {
  buildPageUrl,
  computePaginationRange,
} from "@/modules/setting/modules/trustedDevices/utils/pagination";

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

interface TrustedDevicesPaginationProps {
  onPerPageChange: (value: number) => void;
  pagination: TrustedDevicePagination;
}

function TrustedDevicesPagination({
  onPerPageChange,
  pagination,
}: TrustedDevicesPaginationProps): JSX.Element {
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

        <Select
          value={String(pagination.per_page)}
          onValueChange={(value) => {
            onPerPageChange(Number.parseInt(value, 10));
          }}
        >
          <SelectTrigger className="w-full max-w-32">
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
      preserveScroll
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
      preserveScroll
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
      preserveScroll
    >
      <ChevronRight className="size-4" />
    </Link>
  );
}

export default TrustedDevicesPagination;
